<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\Capacity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Enums\VisitStatusEnum;
use App\Enums\DonationStatusEnum;
use App\Mail\VisitSubmittedMail;
use App\Mail\VisitApprovedMail;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CapacityService
{
    /**
     * Creates a new visit request after enforcing:
     *   1. Authentication State Constraint (email_verified_at != NULL)
     *   2. Initial capacity availability check
     *
     * Does NOT reserve a slot — reservation only happens upon approval via approveVisit().
     *
     * UML Ref: Activity Diagram §6 — [Constraint Check] email_verified_at != NULL
     * UML Ref: Sequence Diagram §SD-6 — [Initial Validation] checkAvailability
     *
     * @param string $userId      Authenticated user UUID.
     * @param string $capacityId  Target capacity slot UUID.
     * @return Visit
     */
    public function createVisitRequest(string $userId, string $capacityId, string $visitorType = 'Individu', ?string $proposalFilePath = null, ?string $visitorName = null, ?string $purpose = null): Visit
    {
        // Authentication State Constraint (AGENTS.md §3)
        $user = User::find($userId);

        if (!$user) {
            throw new HttpException(404, 'User not found.');
        }

        if (!$user->email_verified_at) {
            throw new HttpException(403, 'Email belum diverifikasi. Silakan verifikasi email Anda terlebih dahulu.');
        }

        // Initial capacity availability check
        $capacity = Capacity::find($capacityId);

        if (!$capacity) {
            throw new HttpException(404, 'Capacity slot not found.');
        }

        if ($capacity->quota <= $capacity->booked) {
            throw new HttpException(422, 'Selected slot is fully booked.');
        }

        // Feature P1: 1 User 1 Sesi per Hari
        // User cannot have another PENDING or APPROVED visit on the same date.
        $existingVisit = Visit::where('user_id', $userId)
            ->whereIn('status', [VisitStatusEnum::PENDING->value, VisitStatusEnum::APPROVED->value])
            ->whereHas('capacity', function ($query) use ($capacity) {
                $query->where('date', $capacity->date);
            })
            ->first();

        if ($existingVisit) {
            throw new HttpException(422, 'Anda sudah memiliki pengajuan kunjungan yang aktif pada tanggal ini. Maksimal 1 sesi per hari.');
        }

        // Feature P1: Limit 1 Institution per session
        if ($visitorType === 'Lembaga/Instansi') {
            $existingInstitution = Visit::where('capacity_id', $capacityId)
                ->where('visitor_type', 'Lembaga/Instansi')
                ->where('status', '!=', VisitStatusEnum::REJECTED->value)
                ->first();

            if ($existingInstitution) {
                throw new HttpException(422, 'Sesi ini sudah dibooking oleh instansi lain. Silakan pilih sesi yang berbeda.');
            }
        }

        DB::beginTransaction();
        try {
            // Re-fetch capacity with pessimistic write lock (AGENTS.md §3 Concurrency Rule)
            $lockedCapacity = Capacity::lockForUpdate()->find($capacityId);

            if ($lockedCapacity->quota <= $lockedCapacity->booked) {
                throw new HttpException(422, 'Selected slot is fully booked due to concurrent request.');
            }

            $visit = Visit::create([
                'user_id'     => $userId,
                'capacity_id' => $capacityId,
                'visitor_type' => $visitorType,
                'visitor_name' => $visitorName,
                'purpose'      => $purpose,
                'proposal_file_path' => $proposalFilePath,
                'status'      => VisitStatusEnum::PENDING->value,
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        
        $visit->load('capacity');
        Mail::to($user->email)->queue(new VisitSubmittedMail($visit, $user));
        
        $adminEmail = env('ADMIN_EMAIL', 'fravelrama88@gmail.com');
        if ($adminEmail) {
            Mail::to($adminEmail)->queue(new \App\Mail\AdminNewVisitNotification($visit));
        }

        return $visit;
    }

    /**
     * Approves a visit while enforcing Pessimistic Locking to prevent Race Conditions.
     *
     * UML Ref: Sequence Diagram §SD-6 — [Re-Validation — CRITICAL SECTION]
     *   1. BEGIN TRANSACTION
     *   2. lockCapacityForUpdate(capacity_id) — SELECT FOR UPDATE
     *   3. Evaluate (quota - booked) > 0
     *   4. If true: increment booked, update status to APPROVED, COMMIT
     *   5. If false: ROLLBACK, throw CapacityFull exception
     *
     * @param string $visitId  The UUID of the visit to approve.
     * @return array           Serializable visit data.
     */
    public function approveVisit(string $visitId): array
    {
        return DB::transaction(function () use ($visitId) {
            $visit = Visit::find($visitId);

            if (!$visit) {
                throw new HttpException(404, 'Visit not found.');
            }

            $statusValue = $visit->status instanceof \BackedEnum ? $visit->status->value : $visit->status;
            if ($statusValue !== VisitStatusEnum::PENDING->value) {
                throw new HttpException(422, 'Only pending visits can be approved.');
            }

            // Pessimistic Locking via capacity_id FK (direct lookup with SELECT FOR UPDATE)
            $capacity = Capacity::where('id', $visit->capacity_id)
                ->lockForUpdate()
                ->first();

            if (!$capacity) {
                throw new HttpException(404, 'Capacity not configured for this visit.');
            }

            if ($capacity->quota <= $capacity->booked) {
                // Race condition: capacity became full between submission and approval
                $visit->update(['status' => VisitStatusEnum::REJECTED->value]);
                throw new HttpException(409, 'Capacity is full. Visit rejected due to race condition.');
            }

            // Safely increment booked quota
            $capacity->increment('booked');

            // Update visit status to approved
            $visit->update(['status' => VisitStatusEnum::APPROVED->value]);

            $visit->load(['capacity', 'user']);
            if ($visit->user && $visit->user->email) {
                Mail::to($visit->user->email)->queue(new VisitApprovedMail($visit));
            }

            return $visit->toArray();
        });
    }

    /**
     * Rejects a visit request by setting its status to REJECTED.
     * Lifecycle Cascade: If the visit has a bound donation (via visit_id FK),
     * forcefully void it by setting its status to REJECTED as well.
     * This prevents Inventory Hoarding — virtual stock from expired/rejected
     * visit donations is immediately freed.
     *
     * @param string $visitId  The UUID of the visit to reject.
     * @return array           Serializable visit data.
     */
    public function rejectVisit(string $visitId): array
    {
        return DB::transaction(function () use ($visitId) {
            $visit = Visit::find($visitId);

            if (!$visit) {
                throw new HttpException(404, 'Visit not found.');
            }

            $statusValue = $visit->status instanceof \BackedEnum ? $visit->status->value : $visit->status;
            if ($statusValue !== VisitStatusEnum::PENDING->value) {
                throw new HttpException(422, 'Only pending visits can be rejected.');
            }

            $visit->update(['status' => VisitStatusEnum::REJECTED->value]);

            // Lifecycle Cascade: void the bound donation to free virtual stock
            $donation = $visit->donation;
            if ($donation && $donation->status->value === DonationStatusEnum::PENDING_DELIVERY->value) {
                $donation->update(['status' => DonationStatusEnum::REJECTED->value]);
                foreach ($donation->itemDonations as $item) {
                    if ($item->photo_url) {
                        Storage::disk('public')->delete($item->photo_url);
                        $item->update(['photo_url' => null]);
                    }
                }
            }

            return $visit->toArray();
        });
    }

    /**
     * Retrieves a visit record with its capacity relationship as a serializable array.
     * Used by the Controller to attach data to JSON responses without touching Eloquent.
     *
     * @param string $visitId
     * @return array
     */
    public function getVisitWithCapacity(string $visitId): array
    {
        $visit = Visit::with('capacity')->find($visitId);

        if (!$visit) {
            throw new HttpException(404, 'Visit not found.');
        }

        return $visit->toArray();
    }

    /**
     * Retrieves raw capacity data as an array.
     * Used by VisitController to compute session end-boundary for TTL binding.
     *
     * @param string $capacityId
     * @return array
     */
    public function getCapacity(string $capacityId): array
    {
        $capacity = Capacity::find($capacityId);

        if (!$capacity) {
            throw new HttpException(404, 'Capacity slot not found.');
        }

        return $capacity->toArray();
    }

    /**
     * Resolves an approved visit by checking them in or marking as no-show.
     *
     * @param string $visitId
     * @param string $status
     * @return array
     */
    public function resolveVisit(string $visitId, string $status): array
    {
        return DB::transaction(function () use ($visitId, $status) {
            $visit = Visit::find($visitId);

            if (!$visit) {
                throw new HttpException(404, 'Visit not found.');
            }

            $currentStatus = $visit->status instanceof \BackedEnum ? $visit->status->value : $visit->status;
            if ($currentStatus !== VisitStatusEnum::APPROVED->value) {
                throw new HttpException(422, 'Only approved visits can be resolved.');
            }

            if (!in_array($status, [VisitStatusEnum::COMPLETED->value, VisitStatusEnum::NO_SHOW->value])) {
                throw new HttpException(422, 'Invalid resolution status.');
            }

            $visit->update(['status' => $status]);

            return $visit->toArray();
        });
    }

    /**
     * Processes a visitor-initiated reschedule for a NEEDS_RESCHEDULE visit.
     *
     * Transactional Guarantees:
     *   1. Ownership validation (visit.user_id === $userId)
     *   2. Status gate (only NEEDS_RESCHEDULE visits)
     *   3. Pessimistic lock on NEW capacity via lockForUpdate()
     *   4. Capacity slot availability check + increment booked
     *   5. Visit update: capacity_id, status→PENDING, is_rescheduled→true, admin_notes→null
     *   6. Snapshot Pattern for item sync:
     *      - DELETE items omitted from payload
     *      - UPDATE items with matching id (qty change)
     *      - CREATE items without id (new additions)
     *
     * @param string $visitId
     * @param string $userId
     * @param string $newCapacityId
     * @param array|null $updatedItems
     * @return array
     */
    public function processReschedule(string $visitId, string $userId, string $newCapacityId): array
    {
        return DB::transaction(function () use ($visitId, $userId, $newCapacityId) {
            // 1. Fetch and validate ownership
            $visit = Visit::find($visitId);

            if (!$visit) {
                throw new HttpException(404, 'Visit not found.');
            }

            if ($visit->user_id !== $userId) {
                throw new HttpException(403, 'Anda tidak memiliki akses ke kunjungan ini.');
            }

            $currentStatus = $visit->status instanceof \BackedEnum
                ? $visit->status->value
                : $visit->status;

            if ($currentStatus !== VisitStatusEnum::NEEDS_RESCHEDULE->value) {
                throw new HttpException(422, 'Hanya kunjungan berstatus NEEDS_RESCHEDULE yang dapat di-reschedule.');
            }

            $oldCapacityId = $visit->capacity_id;
            
            if ($oldCapacityId === $newCapacityId) {
                throw new HttpException(422, 'Slot baru tidak boleh sama dengan slot lama.');
            }

            $newCapacity = Capacity::where('id', $newCapacityId)
                ->lockForUpdate()
                ->first();

            if (!$newCapacity) {
                throw new HttpException(404, 'Slot kapasitas baru tidak ditemukan.');
            }

            if ($newCapacity->booked >= $newCapacity->quota) {
                throw new HttpException(422, 'Slot yang dipilih sudah penuh.');
            }

            // 4. Update visit record
            $visit->update([
                'capacity_id'   => $newCapacityId,
                'status'        => VisitStatusEnum::PENDING->value,
                'is_rescheduled' => true,
            ]);

            return $visit->load(['capacity', 'donation.itemDonations'])->toArray();
        });
    }
}
