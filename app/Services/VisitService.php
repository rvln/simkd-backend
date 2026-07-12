<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\Capacity;
use App\Enums\VisitStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitService
{
    /**
     * Fetch visits eager loaded with user and capacity, with dynamic filters.
     * Filters: status (string), date (string YYYY-MM-DD), search (string).
     */
    public function getVisits(array $filters = [])
    {
        $query = Visit::with(['user', 'capacity', 'donation.itemDonations']);

        if (!empty($filters['status']) && $filters['status'] !== 'ALL') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date'])) {
            $query->whereHas('capacity', function ($q) use ($filters) {
                $q->where('date', $filters['date']);
            });
        }

        if (!empty($filters['search'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('name', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Approve a visit ensuring max 1 visit per session block.
     *
     * @param string $id
     * @param string|null $confirmedTime
     * @return Visit
     * @throws ValidationException
     */
    public function approveVisit(string $id, ?string $confirmedTime)
    {
        return DB::transaction(function () use ($id, $confirmedTime) {
            $visit = Visit::findOrFail($id);

            if ($visit->status !== VisitStatusEnum::PENDING) {
                throw ValidationException::withMessages(['status' => 'Kunjungan ini sudah diproses.']);
            }

            // Lock the capacity to prevent race conditions
            $capacity = Capacity::where('id', $visit->capacity_id)->lockForUpdate()->firstOrFail();

            // Mutex Guard: Enforce maximum 1 visit per session (or according to quota if quota is 1, but prompt says strictly 1-visit-per-session)
            if ($capacity->booked >= 1) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi ini sudah diisi oleh kunjungan lain. Maksimal 1 kunjungan per sesi.'
                ]);
            }

            $capacity->booked += 1;
            $capacity->save();

            $visit->status = VisitStatusEnum::APPROVED;
            if ($confirmedTime) {
                $visit->confirmed_time = $confirmedTime;
            }
            $visit->save();

            $visit->load(['capacity', 'user']);
            if ($visit->user && $visit->user->email) {
                \Illuminate\Support\Facades\Mail::to($visit->user->email)->queue(new \App\Mail\VisitApprovedMail($visit));
            }

            return $visit;
        });
    }

    /**
     * Reject a visit.
     *
     * @param string $id
     * @param string $reason
     * @return Visit
     */
    public function rejectVisit(string $id, string $reason)
    {
        return DB::transaction(function () use ($id, $reason) {
            $visit = Visit::lockForUpdate()->findOrFail($id);

            $currentStatus = $visit->status instanceof \BackedEnum ? $visit->status->value : $visit->status;
            $allowedStatuses = [
                VisitStatusEnum::PENDING->value,
                VisitStatusEnum::APPROVED->value,
                VisitStatusEnum::NEEDS_RESCHEDULE->value,
            ];

            if (!in_array($currentStatus, $allowedStatuses, true)) {
                throw ValidationException::withMessages(['status' => 'Kunjungan ini sudah diproses dan tidak dapat ditolak.']);
            }

            // Capacity Release Guard
            // Under "Reserve-on-Approval", only APPROVED visits hold a slot.
            if ($currentStatus === VisitStatusEnum::APPROVED->value) {
                $capacity = Capacity::where('id', $visit->capacity_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($capacity->booked > 0) {
                    $capacity->decrement('booked');
                }
            }

            $visit->status = VisitStatusEnum::REJECTED;
            $visit->rejection_reason = $reason;
            $visit->is_rescheduled = false; // Strict state reset
            $visit->save();

            $visit->load(['user']);
            if ($visit->user && $visit->user->email) {
                \Illuminate\Support\Facades\Mail::to($visit->user->email)->queue(new \App\Mail\VisitRejectedMail($visit));
            }

            return $visit;
        });
    }

    /**
     * Mark a visit as needing reschedule and store the admin's recommendation.
     *
     * Capacity Release Rule (Concurrency Fix):
     *   If the visit was previously APPROVED, its capacity slot was already
     *   reserved (booked incremented). We MUST release that slot immediately
     *   by decrementing `booked` under pessimistic lock to prevent phantom
     *   slot hoarding and ensure the capacity is available for other visitors.
     *
     *   If the visit was PENDING, no capacity was reserved, so no release needed.
     *
     * @param string $id
     * @param string $recommendationNotes
     * @return Visit
     */
    public function requestReschedule(string $id, string $recommendationNotes)
    {
        return DB::transaction(function () use ($id, $recommendationNotes) {
            $visit = Visit::findOrFail($id);

            $currentStatus = $visit->status instanceof \BackedEnum
                ? $visit->status->value
                : $visit->status;

            $allowedStatuses = [
                VisitStatusEnum::PENDING->value,
                VisitStatusEnum::APPROVED->value,
            ];

            if (!in_array($currentStatus, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya kunjungan berstatus PENDING atau APPROVED yang dapat diminta jadwal ulang.',
                ]);
            }

            // Capacity Release: if visit was APPROVED, the slot was reserved — release it now
            if ($currentStatus === VisitStatusEnum::APPROVED->value) {
                $capacity = Capacity::where('id', $visit->capacity_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($capacity->booked > 0) {
                    $capacity->decrement('booked');
                }
            }

            $visit->status = VisitStatusEnum::NEEDS_RESCHEDULE;
            $visit->admin_notes = $recommendationNotes;
            $visit->save();

            $visit->load(['user']);
            if ($visit->user && $visit->user->email) {
                \Illuminate\Support\Facades\Mail::to($visit->user->email)->queue(new \App\Mail\VisitRescheduleMail($visit));
            }

            return $visit;
        });
    }
}
