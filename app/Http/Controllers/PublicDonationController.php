<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Donation;
use App\Services\InventoryService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use App\Enums\DonationStatusEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Enums\DonationTypeEnum;

class PublicDonationController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private PaymentService   $paymentService,
    ) {}

    /**
     * Store a newly created item donation from the public form.
     *
     * AGENTS.md §2 Compliance: Controller handles ONLY input validation
     * and service delegation. All business logic (locking, TTL, capacity checks)
     * lives in InventoryService::submitPublicDonation().
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'donorName'      => 'required|string|max:255',
            'donorPhone'     => 'required|string|max:255',
            'items'          => 'required|array|min:1',
            'items.*.id'     => 'required|string',
            'items.*.name'   => 'required_if:items.*.id,MANUAL|string|max:255',
            'items.*.qty'    => 'required|integer|min:1',
            'items.*.image'  => 'nullable|image|max:5120',
        ]);

        try {
            $donation = $this->inventoryService->submitPublicDonation(
                [
                    'donorName'  => $validated['donorName'],
                    'donorPhone' => $validated['donorPhone'],
                    'donorEmail' => $request->input('donorEmail', null),
                ],
                $validated['items']
            );

            return response()->json([
                'status'        => 'success',
                'tracking_code' => $donation->tracking_code,
            ], 201);
        } catch (ValidationException $e) {
            // Clean 422 JSON for frontend interception
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * Retrieve a donation by its tracking code for public hydration.
     */
    public function show($tracking_code)
    {
        $donation = Donation::with('itemDonations')
            ->where('tracking_code', $tracking_code)
            ->first();

        if (!$donation) {
            return response()->json(['message' => 'Resi tidak ditemukan.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $donation
        ]);
    }

    /**
     * Cancel a pending public financial donation.
     */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'tracking_code' => 'required|string',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $donation = Donation::findOrFail($id);

            // Constant-time string comparison for tracking_code security
            if (!hash_equals($donation->tracking_code, $request->input('tracking_code'))) {
                return response()->json(['message' => 'Tracking code tidak valid atau Anda tidak memiliki akses untuk membatalkan donasi ini.'], 403);
            }

            if ($donation->status->value !== DonationStatusEnum::PENDING->value) {
                return response()->json(['message' => 'Hanya donasi dengan status PENDING yang dapat dibatalkan.'], 422);
            }

            $donation->update(['status' => DonationStatusEnum::EXPIRED->value]);

            return response()->json(['status' => 'success', 'message' => 'Donasi berhasil dibatalkan.']);
        });
    }

    /**
     * Show invoice data for a specific financial donation.
     * Returns data immediately regardless of payment status.
     * Status label is resolved here so frontend can display the correct state.
     */
    public function showInvoice($id)
    {
        $donation = Donation::find($id);

        if (!$donation) {
            return response()->json(['message' => 'Faktur tidak ditemukan.'], 404);
        }

        if ($donation->type->value !== DonationTypeEnum::DANA->value) {
            return response()->json(['message' => 'Faktur ini bukan untuk donasi finansial.'], 403);
        }

        // Pull-based sync: if still PENDING, query Midtrans API directly for latest status.
        // This is the fallback for when the webhook (push) has not arrived or failed.
        if ($donation->status->value === DonationStatusEnum::PENDING->value) {
            $donation = $this->paymentService->syncPaymentStatus($donation);
        }

        // Resolve a human-readable status label
        $statusValue = $donation->status->value;
        $statusLabel = match ($statusValue) {
            'SUCCESS'  => 'Pembayaran Berhasil',
            'PENDING'  => 'Menunggu Pembayaran',
            'FAILED'   => 'Pembayaran Gagal',
            'EXPIRED'  => 'Kadaluarsa',
            default    => $statusValue,
        };

        // PII Masking for public-facing fields
        $donorEmail = $donation->donorEmail ? $this->maskEmail($donation->donorEmail) : null;
        $donorPhone = $donation->donorPhone ? $this->maskPhone($donation->donorPhone) : null;

        return response()->json([
            'status' => 'ok',
            'data'   => [
                'id'             => $donation->id,
                'tracking_code'  => $donation->tracking_code ?? $donation->order_id,
                'amount'         => $donation->amount,
                'payment_type'   => $donation->payment_type,
                'payment_status' => $statusValue,
                'status_label'   => $statusLabel,
                'created_at'     => $donation->created_at,
                'donorName'      => $donation->donorName,
                'donorEmail'     => $donorEmail,
                'donorPhone'     => $donorPhone,
                'snap_token'     => $donation->snap_token,
            ]
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');

    }


    /**
     * Generate and download PDF for a successful donation.
     */
    public function downloadPdf($id)
    {
        $donation = Donation::find($id);

        if (!$donation || $donation->status->value !== DonationStatusEnum::SUCCESS->value) {
            abort(404, 'Faktur tidak ditemukan atau belum lunas.');
        }

        $pdf = Pdf::loadView('pdf.donation-invoice', compact('donation'));
        return $pdf->download('invoice-' . ($donation->tracking_code ?? $donation->order_id ?? $donation->id) . '.pdf');
    }

    private function maskEmail($email) {
        $parts = explode('@', $email);
        if (count($parts) != 2) return $email;
        $name = $parts[0];
        $maskedName = strlen($name) > 2 ? substr($name, 0, 1) . str_repeat('*', strlen($name) - 2) . substr($name, -1) : $name;
        return $maskedName . '@' . $parts[1];
    }

    private function maskPhone($phone) {
        if (strlen($phone) < 6) return $phone;
        return substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 6) . substr($phone, -2);
    }
}
