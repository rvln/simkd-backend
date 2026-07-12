<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\Inventory;
use App\Models\RejectedLog;
use App\Enums\DonationStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DonationValidationService
{
    /**
     * Retrieve donations with dynamic filtering.
     * Eager loads itemDonations → inventory so the frontend can render item details.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDonations(array $filters = [])
    {
        $query = Donation::with(['itemDonations.inventory']);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where(function ($q) use ($filters) {
                if ($filters['status'] === 'ALL') {
                    // Do not apply any status filtering
                } elseif ($filters['status'] === 'EXPIRED') {
                    $q->where('status', DonationStatusEnum::PENDING_DELIVERY->value)
                      ->whereNotNull('expires_at')
                      ->where('expires_at', '<', now());
                } elseif ($filters['status'] === 'PENDING_DELIVERY') {
                    $q->where(function ($sq) {
                        $sq->where('status', DonationStatusEnum::PENDING_DELIVERY->value)
                          ->where(function ($ssq) {
                              $ssq->whereNull('expires_at')
                                 ->orWhere('expires_at', '>=', now());
                          });
                    })->orWhere(function ($sq) {
                        $sq->where('status', DonationStatusEnum::PENDING->value)
                          ->where('payment_channel', 'MANUAL');
                    });
                } elseif ($filters['status'] === 'PENDING_MANUAL') {
                    // Synthetic filter from DANA tab: only PENDING + MANUAL channel
                    $q->where('status', DonationStatusEnum::PENDING->value)
                      ->where('payment_channel', 'MANUAL');
                } elseif ($filters['status'] === 'EXPIRED_FAILED') {
                    // Synthetic filter from DANA tab: terminal states
                    $q->whereIn('status', [
                        DonationStatusEnum::EXPIRED->value,
                        DonationStatusEnum::FAILED->value,
                    ]);
                } else {
                    $q->where('status', $filters['status']);
                }
            });
        } elseif (empty($filters['status'])) {
            // Default: active PENDING_DELIVERY and active PENDING (MANUAL)
            $query->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('status', DonationStatusEnum::PENDING_DELIVERY->value)
                      ->where(function ($ssq) {
                          $ssq->whereNull('expires_at')
                             ->orWhere('expires_at', '>=', now());
                      });
                })->orWhere(function ($sq) {
                    $sq->where('status', DonationStatusEnum::PENDING->value)
                      ->where('payment_channel', 'MANUAL');
                });
            });
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('tracking_code', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('donorName', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('id', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhereHas('itemDonations', function ($sq) use ($filters) {
                      $sq->where('itemName_snapshot', 'LIKE', '%' . $filters['search'] . '%');
                  });
            });
        }

        $query->when(!empty($filters['date']), function ($q) use ($filters) {
            $q->whereDate('created_at', $filters['date']);
        });

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Approve a donation (Phase 2: Check-in accepted).
     * Atomically:
     *   1. Lock the donation row (prevents race on double-approval)
     *   2. Guard state machine — only PENDING_DELIVERY can be approved
     *   3. Increment inventory stock for each item donation
     *   4. Mark donation as SUCCESS
     *
     * UML Ref: Sequence Diagram §SD-4 — Phase 2: Check-in (Accepted)
     * AGENTS.md §3 — Pessimistic Locking enforced via lockForUpdate()
     *
     * @param string $donationId UUID of the donation to approve.
     * @return array Serializable donation snapshot.
     */
    public function approveDonation(string $donationId): array
    {
        return DB::transaction(function () use ($donationId) {
            // Pessimistic lock — prevents double-approval race condition
            $donation = Donation::where('id', $donationId)
                ->lockForUpdate()
                ->first();

            if (!$donation) {
                throw new HttpException(404, 'Donasi tidak ditemukan.');
            }

            // State machine guard — only PENDING_DELIVERY transitions to SUCCESS
            if ($donation->status->value !== DonationStatusEnum::PENDING_DELIVERY->value) {
                throw new HttpException(
                    422,
                    'Donasi ini sudah diproses sebelumnya dan tidak dapat diubah statusnya.'
                );
            }

            // Load item donations for stock increment
            $donation->load('itemDonations');

            foreach ($donation->itemDonations as $item) {
                if (empty($item->inventory_id)) {
                    // Unplanned / Formulir Bebas logic
                    $newInventory = \App\Models\Inventory::create([
                        'itemName'   => $item->itemName_snapshot ?? 'Barang Donasi',
                        'category'   => 'LAINNYA', // Provide a fallback category
                        'stock'      => $item->qty,
                        'target_qty' => 0,
                        'priority'   => null,
                    ]);
                    $item->update(['inventory_id' => $newInventory->id]);
                } else {
                    // Planned Catalog logic with Pessimistic Locking
                    $inventory = \App\Models\Inventory::where('id', $item->inventory_id)
                        ->lockForUpdate()
                        ->first();

                    if ($inventory) {
                        $inventory->increment('stock', $item->qty);
                    }
                }
            }

            // Transition to SUCCESS
            $donation->status = DonationStatusEnum::SUCCESS->value;
            $donation->save();

            if ($donation->donorEmail) {
                \Illuminate\Support\Facades\Mail::to($donation->donorEmail)->queue(new \App\Mail\ItemDonationApprovedMail($donation));
            }

            return $donation->fresh()->toArray();
        });
    }

    /**
     * Reject a donation (Phase 2: Check-in rejected).
     * Atomically:
     *   1. Guard state machine — only PENDING_DELIVERY can be rejected
     *   2. Update donation status to REJECTED
     *   3. Create RejectedLog with donation_id FK (mandatory audit trail)
     *
     * AGENTS.md §3 — "If rejected during Phase 2, MUST be logged in RejectedLog
     *                 WITH the donation_id (Foreign Key)"
     *
     * @param string $donationId UUID of the donation to reject.
     * @param string $loggedBy   UUID of the authenticated staff member.
     * @param string $reason     Rejection reason (mandatory).
     * @return array Serializable donation snapshot.
     */
    public function rejectDonation(string $donationId, string $loggedBy, string $reason): array
    {
        return DB::transaction(function () use ($donationId, $loggedBy, $reason) {
            $donation = Donation::with('itemDonations')
                ->where('id', $donationId)
                ->lockForUpdate()
                ->first();

            if (!$donation) {
                throw new HttpException(404, 'Donasi tidak ditemukan.');
            }

            if ($donation->status->value !== DonationStatusEnum::PENDING_DELIVERY->value) {
                throw new HttpException(
                    422,
                    'Donasi ini sudah diproses sebelumnya dan tidak dapat ditolak.'
                );
            }

            // Transition to REJECTED
            $donation->status = DonationStatusEnum::REJECTED->value;
            $donation->save();

            // Delete physical image files
            foreach ($donation->itemDonations as $item) {
                if ($item->photo_url) {
                    Storage::disk('public')->delete($item->photo_url);
                    $item->update(['photo_url' => null]);
                }
            }

            // Audit trail — AGENTS.md §3: donation_id FK is mandatory
            $itemNameSnapshot = $donation->itemDonations->first()?->itemName_snapshot ?? 'Donasi Barang';

            RejectedLog::create([
                'donation_id' => $donation->id,
                'itemName'    => $itemNameSnapshot,
                'reason'      => $reason,
                'logged_by'   => $loggedBy,
            ]);

            if ($donation->donorEmail) {
                \Illuminate\Support\Facades\Mail::to($donation->donorEmail)->queue(new \App\Mail\ItemDonationRejectedMail($donation, $reason));
            }

            return $donation->fresh()->toArray();
        });
    }
}
