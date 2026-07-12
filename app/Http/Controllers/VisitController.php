<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitVisitRequest;
use App\Http\Requests\ApproveVisitRequest;
use App\Http\Requests\RescheduleVisitRequest;
use App\Services\CapacityService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VisitController extends Controller
{
    public function __construct(
        private CapacityService $capacityService,
        private InventoryService $inventoryService
    ) {}

    /**
     * POST /api/visits
     * Delegates visit creation + optional Smart Cart donation to Service layer.
     * Controller passes only primitive identifiers — zero Eloquent here.
     */
    public function submitRequest(SubmitVisitRequest $request)
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $userId = $user->id;

            $visitorType = $request->input('visitor_type', 'Individu');
            $visitorName = $request->input('visitor_name');
            $purpose = $request->input('purpose');
            $proposalFilePath = null;

            if ($request->hasFile('proposal_file')) {
                $proposalFilePath = $request->file('proposal_file')->store('documents/proposals', 'public');
            }

            // Delegate visit creation to CapacityService
            // Service independently validates email_verified_at
            $visit = $this->capacityService->createVisitRequest(
                $userId,
                $request->capacity_id,
                $visitorType,
                $proposalFilePath,
                $visitorName,
                $purpose
            );

            // Unified endpoint: if visitor brings donation items, process Smart Cart
            $donation = null;
            if ($request->boolean('bringsDonation') && $request->has('items')) {
                $slotBoundaryMap = [
                    'MORNING'   => '10:00:00',
                    'AFTERNOON' => '15:00:00',
                    'EVENING'   => '18:00:00',
                    'NIGHT'     => '20:00:00',
                ];
                // Access the eager-loaded capacity directly to avoid toArray() ISO serialization
                $visitCapacity = $visit->capacity;
                // Strictly extract Y-m-d to prevent Carbon "Double time specification" error
                // when the date cast outputs a full ISO-8601 string (e.g. 2026-05-10T00:00:00.000Z)
                $visitDate = \Carbon\Carbon::parse($visitCapacity->date)->format('Y-m-d');
                $slotValue = $visitCapacity->slot instanceof \BackedEnum
                    ? $visitCapacity->slot->value
                    : (string) $visitCapacity->slot;
                $boundaryTime = $slotBoundaryMap[$slotValue] ?? '23:59:59';
                // Combine strict date + boundary time — unambiguous input for Carbon
                // Then convert to UTC so the database value + JSON 'Z' suffix are truthful
                $expiresAt = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $visitDate . ' ' . $boundaryTime, 'Asia/Makassar')
                        ->setTimezone('UTC');

                $donation = $this->inventoryService->submitPreSubmission(
                    $userId,
                    [
                        'donorName'  => $user->name,
                        'donorEmail' => $user->email,
                        'donorPhone' => $request->input('donorPhone', ''),
                    ],
                    $request->items,
                    $visit->id,
                    $expiresAt,
                );
            }

            // Retrieve serializable visit data via Service (no Eloquent in Controller)
            $visitData = $this->capacityService->getVisitWithCapacity($visit->id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'visit'    => $visitData,
                    'donation' => $donation,
                ]
            ], 201);
        } catch (HttpException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * PUT /api/admin/visits/{visit}/approve
     * Delegates approval/rejection entirely to CapacityService.
     * Route Model Binding resolves the visit UUID — Controller passes only the ID string.
     */
    public function approveRequest(ApproveVisitRequest $request, string $visit)
    {
        try {
            if ($request->action === 'approve') {
                $this->capacityService->approveVisit($visit);
            } else {
                $this->capacityService->rejectVisit($visit);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Visit request processed.'
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * PATCH /api/visits/{visit}/resolve
     * Resolves an approved visit as COMPLETED or NO_SHOW.
     */
    public function resolve(\Illuminate\Http\Request $request, string $visit)
    {
        $request->validate([
            'status' => 'required|string|in:COMPLETED,NO_SHOW'
        ]);

        try {
            $this->capacityService->resolveVisit($visit, $request->status);

            return response()->json([
                'status' => 'success',
                'message' => 'Visit resolved successfully.'
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * GET /api/user/visits
     * Returns all visits belonging to the authenticated user,
     * grouped by VisitStatusEnum value, with eager-loaded relations.
     */
    public function myVisits()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $visits = \App\Models\Visit::where('user_id', $user->id)
            ->with(['capacity', 'donation.itemDonations'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Group by status value (enum → string) for frontend consumption
        $grouped = [];
        foreach (\App\Enums\VisitStatusEnum::cases() as $status) {
            $grouped[$status->value] = [];
        }

        foreach ($visits as $visit) {
            $statusValue = $visit->status instanceof \BackedEnum
                ? $visit->status->value
                : $visit->status;
            $grouped[$statusValue][] = $visit;
        }

        return response()->json(['data' => $grouped]);
    }

    /**
     * PUT /api/visits/{id}/reschedule
     * Processes a visitor-initiated reschedule for a NEEDS_RESCHEDULE visit.
     * Controller passes only primitives — zero Eloquent.
     */
    public function reschedule(RescheduleVisitRequest $request, string $id)
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $result = $this->capacityService->processReschedule(
                $id,
                $user->id,
                $request->new_capacity_id,
                $request->updated_items,
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Kunjungan berhasil dijadwalkan ulang.',
                'data' => $result,
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
