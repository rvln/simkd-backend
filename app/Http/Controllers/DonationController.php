<?php

namespace App\Http\Controllers;

use App\Http\Requests\InitiateDonationRequest;
use App\Http\Requests\SubmitItemDonationRequest;
use App\Services\PaymentService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Models\Donation;
use App\Enums\DonationStatusEnum;
use Illuminate\Support\Facades\DB;

class DonationController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private InventoryService $inventoryService
    ) {}

    /**
     * POST /api/donations
     * Initiates a financial (DANA) donation. Delegates entirely to PaymentService.
     */
    public function initiateDonation(InitiateDonationRequest $request)
    {
        try {
            $paymentChannel = $request->input('payment_channel', 'MIDTRANS');
            $paymentProof = $request->file('payment_proof');

            $result = $this->paymentService->initiateDonation(
                Auth::id(),
                [
                    'donorName'          => $request->donorName,
                    'donorEmail'         => $request->donorEmail,
                    'donorPhone'         => $request->donorPhone,
                    'donor_name_privacy' => $request->input('donor_name_privacy', 'show'),
                ],
                (float) $request->amount,
                $paymentChannel,
                $paymentProof
            );

            return response()->json([
                'status' => 'success',
                'data'   => $result,
            ], 201);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * POST /api/donations/items
     * Submits an item (BARANG) donation via Smart Cart. Delegates to InventoryService.
     */
    public function submitItemDonation(SubmitItemDonationRequest $request)
    {
        try {
            $validated = $request->validated();

            $donation = $this->inventoryService->submitPreSubmission(
                Auth::id(),
                [
                    'donorName'  => $validated['donorName'],
                    'donorEmail' => $validated['donorEmail'],
                    'donorPhone' => $validated['donorPhone'],
                ],
                $validated['items']
            );

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'tracking_code' => $donation->tracking_code,
                ]
            ], 201);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * PATCH /api/admin/donations/{id}/approve
     */
    public function approveManualDonation($id)
    {
        return DB::transaction(function () use ($id) {
            $donation = Donation::findOrFail($id);

            if ($donation->status->value !== DonationStatusEnum::PENDING->value) {
                return response()->json(['message' => 'Hanya donasi PENDING yang dapat disetujui.'], 422);
            }

            if ($donation->payment_channel !== 'MANUAL') {
                return response()->json(['message' => 'Hanya donasi MANUAL yang dapat disetujui melalui endpoint ini.'], 403);
            }

            $trackingCode = $this->paymentService->generateTrackingCode();

            $donation->update([
                'status' => DonationStatusEnum::SUCCESS->value,
                'tracking_code' => $trackingCode,
            ]);

            if ($donation->donorEmail) {
                \Illuminate\Support\Facades\Mail::to($donation->donorEmail)->queue(new \App\Mail\FinancialDonationSuccessMail($donation));
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Donasi manual berhasil disetujui.',
                'data' => $donation
            ]);
        });
    }

    /**
     * PATCH /api/admin/donations/{id}/reject
     */
    public function rejectManualDonation($id)
    {
        return DB::transaction(function () use ($id) {
            $donation = Donation::findOrFail($id);

            if ($donation->status->value !== DonationStatusEnum::PENDING->value) {
                return response()->json(['message' => 'Hanya donasi PENDING yang dapat ditolak.'], 422);
            }

            if ($donation->payment_channel !== 'MANUAL') {
                return response()->json(['message' => 'Hanya donasi MANUAL yang dapat ditolak melalui endpoint ini.'], 403);
            }

            $donation->update(['status' => DonationStatusEnum::REJECTED->value]);

            if ($donation->donorEmail) {
                \Illuminate\Support\Facades\Mail::to($donation->donorEmail)->queue(new \App\Mail\FinancialDonationRejectedMail($donation));
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Donasi manual berhasil ditolak.',
                'data' => $donation
            ]);
        });
    }
    /**
     * GET /api/user/donations
     * Returns all donations belonging to the authenticated user,
     * with eager-loaded itemDonations for BARANG-type records.
     */
    public function myDonations()
    {
        $user = Auth::user();

        $donations = Donation::where(function ($q) use ($user) {
                // Primary: donations explicitly linked by user ID (logged-in donations)
                $q->where('user_id', $user->id);

                // Fallback: donations made as guest but with matching email
                if ($user->email) {
                    $q->orWhere(function ($q2) use ($user) {
                        $q2->whereNull('user_id')
                           ->where('donorEmail', $user->email);
                    });
                }
            })
            ->with('itemDonations')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($d) => [
                'id'               => $d->id,
                'type'             => $d->type instanceof \App\Enums\DonationTypeEnum ? $d->type->value : $d->type,
                'status'           => $d->status instanceof \App\Enums\DonationStatusEnum ? $d->status->value : $d->status,
                'amount'           => $d->amount,
                'snap_token'       => $d->snap_token,
                'payment_channel'  => $d->payment_channel,
                'tracking_code'    => $d->tracking_code,
                'created_at'       => $d->created_at?->toIso8601String(),
                'item_donations'   => $d->itemDonations->map(fn ($it) => [
                    'id'                 => $it->id,
                    'itemName_snapshot'  => $it->itemName_snapshot,
                    'qty'                => $it->qty,
                ]),
            ]);

        return response()->json(['data' => $donations]);
    }
}
