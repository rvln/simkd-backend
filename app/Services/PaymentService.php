<?php

namespace App\Services;

use Exception;
use App\Models\Donation;
use App\Enums\DonationStatusEnum;
use App\Enums\DonationTypeEnum;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Midtrans\Config;
use Midtrans\Snap;

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
                    return [
                        'donation' => $donation,
                    ];
                }

                // Midtrans logic
                $orderId = 'DON-' . Str::uuid();

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
                ];

                // Request Token from Midtrans
                $snapToken = Snap::getSnapToken($params);

                // Save the token and order_id back to the database
                $donation->update([
                    'order_id'   => $orderId,
                    'snap_token' => $snapToken,
                ]);

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