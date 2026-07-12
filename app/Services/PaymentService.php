<?php

namespace App\Services;

use Exception;
use App\Models\Donation;
use App\Enums\DonationStatusEnum;
use App\Enums\DonationTypeEnum;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;
use App\Mail\FinancialDonationSuccessMail;

class PaymentService
{
    /**
     * Initialize Midtrans Configuration
     */
    public function __construct()
    {
        Config::$serverKey    = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized  = config('midtrans.is_sanitized');
        Config::$is3ds        = config('midtrans.is_3ds');
    }

    /**
     * Initiate a financial (DANA) donation and return Snap Token.
     * Creates a Donation record with status=PENDING and type=DANA.
     * tracking_code is NOT generated here — it is generated upon confirmed SUCCESS via webhook.
     *
     * @param string|null $userId  Authenticated user UUID (nullable for guest donors).
     * @param array $donorData     ['donorName', 'donorEmail', 'donorPhone']
     * @param float $amount        Donation amount.
     * @return array               [snap_token, donation]
     */
    public function initiateDonation(?string $userId, array $donorData, float $amount, string $paymentChannel = 'MIDTRANS', $paymentProof = null): array
    {
        return DB::transaction(function () use ($userId, $donorData, $amount, $paymentChannel, $paymentProof) {
            $proofPath = null;
            if ($paymentChannel === 'MANUAL' && $paymentProof) {
                $proofPath = $paymentProof->store('payment_proofs', 'public');
            }

            try {
                $donation = Donation::create([
                    'user_id'            => $userId,
                    'donorName'          => $donorData['donorName'],
                    'donorEmail'         => $donorData['donorEmail'],
                    'donorPhone'         => $donorData['donorPhone'],
                    'donor_name_privacy' => $donorData['donor_name_privacy'] ?? 'show',
                    'type'               => DonationTypeEnum::DANA->value,
                    'amount'             => $amount,
                    'status'             => DonationStatusEnum::PENDING->value,
                    'payment_channel'    => $paymentChannel,
                    'payment_proof'      => $proofPath,
                ]);

                if ($paymentChannel === 'MANUAL') {
                    // tracking_code MUST remain null for financial donations.
                    // DO NOT call Midtrans API.
                    $adminEmail = env('ADMIN_EMAIL', 'fravelrama88@gmail.com');
                    if ($adminEmail) {
                        \Illuminate\Support\Facades\Mail::to($adminEmail)->queue(new \App\Mail\AdminNewDonationNotification($donation));
                    }
                    if ($donation->donorEmail) {
                        \Illuminate\Support\Facades\Mail::to($donation->donorEmail)->queue(new \App\Mail\ManualDonationSubmittedMail($donation));
                    }
                    return [
                        'donation' => $donation,
                    ];
                }

                // Midtrans logic
                $orderId = 'DON-' . Str::uuid();

                $frontendUrl = request()->header('origin') ?? config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
                // Remove trailing slash if present
                $frontendUrl = rtrim($frontendUrl, '/');

                $params = [
                    'transaction_details' => [
                        'order_id'     => $orderId,
                        'gross_amount' => (int) $amount,
                    ],
                    'customer_details' => [
                        'first_name' => $donorData['donorName'],
                        'email'      => $donorData['donorEmail'],
                        'phone'      => $donorData['donorPhone'],
                    ],
                    // Redirect URLs: Midtrans will redirect the browser here after payment.
                    // This fixes the "https://example.com" redirect bug (BUG-03 Scenario B).
                    // Using $donation->id (UUID) so the invoice page can poll by its primary key.
                    'callbacks' => [
                        'finish'   => "{$frontendUrl}/donasi/invoice/{$donation->id}",
                        'unfinish' => "{$frontendUrl}/donasi/invoice/{$donation->id}",
                        'error'    => "{$frontendUrl}/donasi/invoice/{$donation->id}",
                    ],
                    'gopay' => [
                        'enable_callback' => true,
                        'callback_url' => "{$frontendUrl}/donasi/invoice/{$donation->id}"
                    ],
                    'shopeepay' => [
                        'callback_url' => "{$frontendUrl}/donasi/invoice/{$donation->id}"
                    ]
                ];

                // Request Token from Midtrans
                $snapToken = Snap::getSnapToken($params);

                // Save the token and order_id back to the database
                $donation->update([
                    'order_id'   => $orderId,
                    'snap_token' => $snapToken,
                ]);

                $adminEmail = env('ADMIN_EMAIL', 'fravelrama88@gmail.com');
                if ($adminEmail) {
                    \Illuminate\Support\Facades\Mail::to($adminEmail)->queue(new \App\Mail\AdminNewDonationNotification($donation));
                }

                // Send email to donor if email is provided
                if (!empty($donorData['donorEmail'])) {
                    $invoiceUrl = "{$frontendUrl}/donasi/invoice/{$donation->id}";
                    \Illuminate\Support\Facades\Mail::to($donorData['donorEmail'])->queue(new \App\Mail\PendingPaymentMail($donation, $invoiceUrl));
                }

                return [
                    'snap_token' => $snapToken,
                    'donation'   => $donation,
                ];

            } catch (Exception $e) {
                if ($proofPath) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($proofPath);
                }

                Log::error('Donation Initiation Error: ' . $e->getMessage(), [
                    'userId' => $userId,
                ]);
                throw new HttpException(502, 'Terjadi kesalahan saat memproses donasi.');
            }
        });
    }

    /**
     * Process a Midtrans webhook callback with strict Idempotency Guard.
     *
     * @param array $payload  Raw webhook payload from Midtrans.
     * @return bool           True if processed successfully (including idempotent hits).
     */
    public function processWebhook(array $payload): bool
    {
        $orderId           = $payload['order_id'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;

        if (!$orderId || !$transactionStatus) {
            throw new HttpException(400, 'Missing required webhook fields.');
        }

        // Cryptographic Verification: SHA512 HMAC validation
        $signatureKey      = $payload['signature_key'] ?? '';
        $statusCode        = $payload['status_code'] ?? '';
        $grossAmount       = $payload['gross_amount'] ?? '';
        $serverKey         = config('midtrans.server_key');

        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if (!hash_equals($expectedSignature, $signatureKey)) {
            Log::warning('Midtrans Webhook Signature Mismatch', [
                'order_id' => $orderId,
                'ip'       => request()->ip(),
            ]);
            throw new HttpException(403, 'Invalid webhook signature.');
        }

        // Lookup order by the globally unique order_id, NOT the local auto-incrementing ID
        $donation = Donation::where('order_id', $orderId)->first();

        if (!$donation) {
            throw new HttpException(404, 'Donation not found.');
        }

        // Idempotency Guard: if the donation is already in a final state, discard silently
        $finalStatuses = [
            DonationStatusEnum::SUCCESS->value,
            DonationStatusEnum::FAILED->value,
            DonationStatusEnum::EXPIRED->value,
        ];

        if (in_array($donation->status->value, $finalStatuses)) {
            Log::info("Webhook idempotency hit for order_id={$orderId}. Status={$donation->status->value}. Discarded.");
            return true;
        }

        // Process based on Midtrans transaction_status
        if ($transactionStatus === 'settlement' || $transactionStatus === 'capture') {
            
            // Atomic Operation: status update + tracking_code generation must be indivisible
            DB::transaction(function () use ($donation, $orderId, $payload) {
    $lockedDonation = Donation::where('id', $donation->id)
        ->lockForUpdate()
        ->first();

    if ($lockedDonation->status->value !== DonationStatusEnum::PENDING->value) {
        Log::critical("State Transition Guard: Blocked illegal transition from {$lockedDonation->status->value} to SUCCESS for order_id={$orderId}.");
        return;
    }

    // Generate tracking_code untuk SEMUA donasi yang berhasil (DANA dan BARANG).
    // Menggunakan generateTrackingCode() untuk collision-safe format TXN-DON-YYYY-XXXXXXXX.
    $trackingCode = $this->generateTrackingCode();

    $lockedDonation->update([
        'status'        => DonationStatusEnum::SUCCESS->value,
        'payment_type'  => $payload['payment_type'] ?? $lockedDonation->payment_type,
        'tracking_code' => $trackingCode,
    ]);

    // Dispatch email asynchronously if email is present
    if ($lockedDonation->donorEmail) {
        Mail::to($lockedDonation->donorEmail)->queue(new FinancialDonationSuccessMail($lockedDonation));
    }
});

        } elseif ($transactionStatus === 'expire') {
            $donation->update(['status' => DonationStatusEnum::EXPIRED->value]);
        } elseif ($transactionStatus === 'cancel' || $transactionStatus === 'deny') {
            $donation->update(['status' => DonationStatusEnum::FAILED->value]);
        }

        return true;
    }

    /**
     * Retrieve limited tracking data for a public tracking query.
     *
     * @param string $trackingCode  The public-facing tracking code.
     * @return array                Limited DTO for public consumption.
     */
    public function getPublicTrackingData(string $trackingCode): array
    {
        $donation = Donation::where('tracking_code', $trackingCode)->first();

        if (!$donation) {
            throw new HttpException(404, 'Data tidak ditemukan');
        }

        $distributionStatus = $this->resolveDistributionStatus($donation);

        return [
            'tracking_code'       => $donation->tracking_code,
            'transaction_date'    => $donation->created_at,
            'payment_status'      => $donation->status,
            'type'                => $donation->type,
            'distribution_status' => $distributionStatus,
        ];
    }

    /**
     * Pull-based payment status sync.
     * Queries Midtrans Transaction Status API directly for the given donation.
     * Used as a fallback when the webhook (push) has not arrived or failed.
     * Safe to call multiple times — idempotency is enforced via lockForUpdate().
     *
     * @param  Donation $donation
     * @return Donation  Fresh donation instance after possible status update.
     */
    public function syncPaymentStatus(Donation $donation): Donation
    {
        // Only sync if still PENDING and has a Midtrans order_id
        if ($donation->status->value !== DonationStatusEnum::PENDING->value) {
            return $donation;
        }

        if (!$donation->order_id) {
            return $donation;
        }

        try {
            // Query Midtrans Transaction Status API
            $status            = Transaction::status($donation->order_id);
            $transactionStatus = $status->transaction_status ?? null;

            if ($transactionStatus === 'settlement' || $transactionStatus === 'capture') {
                DB::transaction(function () use ($donation, $status) {
                    $locked = Donation::where('id', $donation->id)
                        ->lockForUpdate()
                        ->first();

                    // Guard: another process (webhook) may have already updated it
                    if ($locked->status->value !== DonationStatusEnum::PENDING->value) {
                        return;
                    }

                    $trackingCode = $this->generateTrackingCode();

                    $locked->update([
                        'status'        => DonationStatusEnum::SUCCESS->value,
                        'tracking_code' => $trackingCode,
                        'payment_type'  => $status->payment_type ?? $locked->payment_type,
                    ]);

                    if ($locked->donorEmail) {
                        Mail::to($locked->donorEmail)->queue(
                            new FinancialDonationSuccessMail($locked->fresh())
                        );
                    }
                });

                return $donation->fresh();

            } elseif ($transactionStatus === 'expire') {
                $donation->update(['status' => DonationStatusEnum::EXPIRED->value]);
                return $donation->fresh();

            } elseif (in_array($transactionStatus, ['cancel', 'deny'])) {
                $donation->update(['status' => DonationStatusEnum::FAILED->value]);
                return $donation->fresh();
            }
        } catch (Exception $e) {
            // Non-fatal: log and return the original donation.
            // Frontend polling will retry in 5s.
            Log::warning("Midtrans status sync failed for order_id={$donation->order_id}: " . $e->getMessage());
        }

        return $donation;
    }

    /**
     * Generates a unique, immutable tracking code.
     * Format: TXN-DON-YYYY-XXXX
     *
     * @return string
     */
    public function generateTrackingCode(): string
    {
        do {
            // Generate an 8-character random alphanumeric string for high entropy
            $fragment = strtoupper(Str::random(8));
            $year = now()->format('Y');
            $code = "TXN-DON-{$year}-{$fragment}";
        } while (Donation::where('tracking_code', $code)->exists());

        return $code;
    }

    /**
     * Resolve the distribution status for a donation.
     *
     * @param Donation $donation
     * @return string
     */
    private function resolveDistributionStatus(Donation $donation): string
    {
        if ($donation->type->value === DonationTypeEnum::DANA->value) {
            return $donation->status->value === DonationStatusEnum::SUCCESS->value
                ? 'allocated'
                : 'pending';
        }

        $itemDonations = $donation->itemDonations;

        if ($itemDonations->isEmpty()) {
            return 'pending';
        }

        $allDistributed = $itemDonations->every(function ($item) {
            return $item->inventory &&
                   $item->inventory->distributions &&
                   $item->inventory->distributions->isNotEmpty();
        });

        return $allDistributed ? 'distributed' : 'pending';
    }
}