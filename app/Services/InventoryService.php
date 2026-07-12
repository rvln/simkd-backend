<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\ItemDonation;
use App\Models\Inventory;
use App\Models\Distribution;
use App\Models\RejectedLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\ItemDonationSubmittedMail;
use Illuminate\Validation\ValidationException;
use App\Enums\DonationStatusEnum;
use App\Enums\DonationTypeEnum;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InventoryService
{
    /**
     * Phase 1: Pre-Submission logic with Smart Cart support.
     * Validates monthly limits per-item, then creates 1 Donation + N ItemDonation records atomically.
     *
     * UML Ref: Sequence Diagram §SD-4 — Phase 1: Pra-Submission
     *   1. checkMonthlyLimit(item_id, qty)
     *   2. If quota safe → insert(item_donation, status=pending_delivery)
     *
     * @param string|null             $userId     Authenticated user UUID, null for guest donors.
     * @param array                   $donorData  ['donorName', 'donorEmail', 'donorPhone']
     * @param array                   $items      [['inventory_id' => UUID, 'qty' => int], ...]
     * @param string|null             $visitId    Parent visit UUID for lifecycle binding (nullable).
     * @param \DateTimeInterface|null  $expiresAt  Session-bound TTL; null = no TTL.
     * @return Donation
     */
    public function submitPreSubmission(
        ?string $userId,
        array $donorData,
        array $items,
        ?string $visitId = null,
        ?\DateTimeInterface $expiresAt = null
    ): Donation
    {
        $monthlyLimit = (int) config('simdk.monthly_item_limit', 500);

        // Validate monthly limits for EACH item before entering the transaction
        foreach ($items as $item) {
            if (empty($item['inventory_id']) || $item['inventory_id'] === 'MANUAL') {
                continue; // Skip monthly limit check for manual items
            }

            $currentMonthTotal = ItemDonation::where('inventory_id', $item['inventory_id'])
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('qty');

            if (($currentMonthTotal + $item['qty']) > $monthlyLimit) {
                $inventory = Inventory::find($item['inventory_id']);
                throw new HttpException(
                    422,
                    "Batas bulanan tercapai untuk item: " . ($inventory?->itemName ?? $item['inventory_id'])
                );
            }
        }

        return DB::transaction(function () use ($userId, $donorData, $items, $visitId, $expiresAt) {
            $paymentService = app(\App\Services\PaymentService::class);

            $donation = Donation::create([
                'user_id'       => $userId,
                'visit_id'      => $visitId,
                'donorName'     => $donorData['donorName'],
                'donorEmail'    => $donorData['donorEmail'],
                'donorPhone'    => $donorData['donorPhone'],
                'type'          => DonationTypeEnum::BARANG->value,
                'status'        => DonationStatusEnum::PENDING_DELIVERY->value,
                'tracking_code' => $paymentService->generateTrackingCode(),
                'expires_at'    => $expiresAt,
            ]);

            foreach ($items as $item) {
                $photoUrl = null;
                if (isset($item['image']) && $item['image'] instanceof \Illuminate\Http\UploadedFile) {
                    $photoUrl = $item['image']->store('donations/items', 'public');
                }

                if (empty($item['inventory_id']) || $item['inventory_id'] === 'MANUAL') {
                    $donation->itemDonations()->create([
                        'inventory_id'      => null,
                        'itemName_snapshot' => $item['name'] ?? 'Barang Manual',
                        'qty'               => $item['qty'],
                        'photo_url'         => $photoUrl,
                    ]);
                } else {
                    $inventory = Inventory::findOrFail($item['inventory_id']);

                    $donation->itemDonations()->create([
                        'inventory_id'      => $item['inventory_id'],
                        'itemName_snapshot' => $inventory->itemName,
                        'qty'               => $item['qty'],
                        'photo_url'         => $photoUrl,
                    ]);
                }
            }

            return $donation->load('itemDonations');
        });
    }

    /**
     * Submit a public item donation with Soft-Booking TTL and Pre-Commit Double-Check.
     *
     * Atomic Transaction Flow:
     *   1. Create Donation with status=PENDING_DELIVERY, expires_at=now+30h
     *   2. For each catalog item: lockForUpdate() → re-count virtual stock → validate capacity → create ItemDonation
     *   3. For MANUAL items: create ItemDonation with inventory_id=null (no capacity check)
     *
     * The Pre-Commit Double-Check ensures that even if two requests pass the UI-level
     * availability check simultaneously, the second one will be rejected at the database gate.
     *
     * AGENTS.md §3: Pessimistic Locking enforced via lockForUpdate()
     *
     * @param array $donorData  ['donorName', 'donorPhone', 'donorEmail' => nullable]
     * @param array $items      [['id' => UUID|'MANUAL', 'name' => string|null, 'qty' => int], ...]
     * @return Donation
     * @throws ValidationException  If catalog capacity would be exceeded (HTTP 422).
     */
    public function submitPublicDonation(array $donorData, array $items): Donation
    {
        $paymentService = app(\App\Services\PaymentService::class);
        $trackingCode = $paymentService->generateTrackingCode();

        return DB::transaction(function () use ($donorData, $items, $trackingCode) {
            // 1. Create the parent Donation with 30-hour TTL
            $donation = Donation::create([
                'tracking_code' => $trackingCode,
                'donorName'     => $donorData['donorName'],
                'donorEmail'    => $donorData['donorEmail'] ?? null,
                'donorPhone'    => $donorData['donorPhone'],
                'type'          => DonationTypeEnum::BARANG->value,
                'status'        => DonationStatusEnum::PENDING_DELIVERY->value,
                'expires_at'    => now()->addHours(30),
            ]);

            // 2. Process each item in the cart
            foreach ($items as $item) {
                $photoUrl = null;
                if (isset($item['image']) && $item['image'] instanceof \Illuminate\Http\UploadedFile) {
                    $photoUrl = $item['image']->store('donations/items', 'public');
                }

                if ($item['id'] === 'MANUAL') {
                    // MANUAL items: no capacity check, no inventory linkage
                    $donation->itemDonations()->create([
                        'inventory_id'      => null,
                        'itemName_snapshot' => $item['name'],
                        'qty'               => $item['qty'],
                        'photo_url'         => $photoUrl,
                    ]);
                    continue;
                }

                // CATALOG items: Pessimistic Lock + Pre-Commit Double-Check
                $inventory = Inventory::where('id', $item['id'])
                    ->lockForUpdate()
                    ->first();

                if (!$inventory) {
                    throw ValidationException::withMessages([
                        'items' => ["Item inventaris dengan ID {$item['id']} tidak ditemukan."],
                    ]);
                }

                // Redundant Verification: re-count pending virtual stock for THIS specific
                // inventory inside the locked transaction block. This catches race conditions
                // where two requests passed the UI availability check simultaneously.
                $currentVirtual = ItemDonation::where('inventory_id', $item['id'])
                    ->whereHas('donation', function ($q) {
                        $q->where('status', DonationStatusEnum::PENDING_DELIVERY->value)
                          ->where('expires_at', '>', now());
                    })
                    ->sum('qty');

                // Validation Gate: stock + current_virtual + incoming_qty > target = REJECT
                $totalAfter = $inventory->stock + $currentVirtual + $item['qty'];
                if ($totalAfter > $inventory->target_qty) {
                    $remaining = max(0, $inventory->target_qty - $inventory->stock - $currentVirtual);
                    throw ValidationException::withMessages([
                        'items' => [
                            "Kapasitas gudang untuk \"{$inventory->itemName}\" sudah terisi. "
                            . "Sisa kapasitas: {$remaining} {$inventory->unit}."
                        ],
                    ]);
                }

                // Capacity validated — create the ItemDonation
                $donation->itemDonations()->create([
                    'inventory_id'      => $item['id'],
                    'itemName_snapshot' => $inventory->itemName,
                    'qty'               => $item['qty'],
                    'photo_url'         => $photoUrl,
                ]);
            }

            // Dispatch email asynchronously if email is present
            if (!empty($donorData['donorEmail'])) {
                Mail::to($donorData['donorEmail'])->queue(new ItemDonationSubmittedMail($donation));
            }
            
            $adminEmail = env('ADMIN_EMAIL', 'fravelrama88@gmail.com');
            if ($adminEmail) {
                Mail::to($adminEmail)->queue(new \App\Mail\AdminNewDonationNotification($donation));
            }

            return $donation->load('itemDonations');
        });
    }

    /**
     * Phase 2: Check-in logic — accepts an item donation after physical inspection.
     * Atomically updates donation status to SUCCESS, increments inventory stock
     * with pessimistic locking, and triggers async notification.
     *
     * UML Ref: Sequence Diagram §SD-4 — Phase 2: Check-in (Accepted)
     *   1. BEGIN TRANSACTION
     *   2. update(item_donations, status=success)
     *   3. updateInventory(stock = stock + qty) via lockForUpdate()
     *   4. COMMIT
     *   5. [Output Kritis] sendAcceptanceNotification (async fire-and-forget)
     *
     * @param string $donationId  UUID of the donation to check in.
     * @return array              Serializable donation data.
     */
    public function checkInItem(string $donationId): array
    {
        $donation = Donation::with('itemDonations')->find($donationId);

        if (!$donation) {
            throw new HttpException(404, 'Donation not found.');
        }

        if ($donation->status->value !== DonationStatusEnum::PENDING_DELIVERY->value) {
            throw new HttpException(422, 'Only pending delivery donations can be checked in.');
        }

        $result = DB::transaction(function () use ($donation) {
            // Update donation status to SUCCESS
            $donation->update(['status' => DonationStatusEnum::SUCCESS->value]);

            // Increment stock for each item donation with pessimistic locking, or auto-create unplanned items
            foreach ($donation->itemDonations as $item) {
                if ($item->inventory_id) {
                    $inventory = Inventory::where('id', $item->inventory_id)
                        ->lockForUpdate()
                        ->first();

                    if ($inventory) {
                        $inventory->increment('stock', $item->qty);
                    }
                } else {
                    // On-the-Fly Auto-Creation for unplanned items (Donasi Bebas)
                    $inventory = Inventory::create([
                        'itemName'    => $item->itemName_snapshot ?? 'Item Tidak Dikenal',
                        'category'    => 'LAINNYA',
                        'target_qty'  => 0,
                        'stock'       => $item->qty,
                        'unit'        => 'Pcs',
                        'priority'    => null, // Explicitly segregates it from the planned catalog
                    ]);

                    // Bind the generated inventory ID back to the audit trail
                    $item->update(['inventory_id' => $inventory->id]);
                }
            }

            return $donation->fresh()->toArray();
        });

        // Async external notification (fire-and-forget) — UML §SD-4 step 6
        $this->dispatchAcceptanceNotification($donation);

        return $result;
    }

    /**
     * Phase 2: Rejection logic — rejects an item donation after physical inspection.
     * Updates donation status to REJECTED and creates an auditable RejectedLog entry
     * WITH the donation_id FK as required by AGENTS.md §3.
     *
     * UML Ref: Sequence Diagram §SD-4 — Phase 2: Check-in (Rejected)
     *   [Tidak Layak] → InventoryService → Database: insert(rejected_logs)
     *
     * @param string $donationId  UUID of the donation to reject.
     * @param string $loggedBy    UUID of the Pengurus performing the rejection.
     * @param string $reason      Reason for rejection.
     * @return array              Serializable donation data.
     */
    public function rejectItem(string $donationId, string $loggedBy, string $reason): array
    {
        $donation = Donation::with('itemDonations')->find($donationId);

        if (!$donation) {
            throw new HttpException(404, 'Donation not found.');
        }

        if ($donation->status->value !== DonationStatusEnum::PENDING_DELIVERY->value) {
            throw new HttpException(422, 'Only pending delivery donations can be rejected.');
        }

        return DB::transaction(function () use ($donation, $loggedBy, $reason) {
            // Update donation status to REJECTED
            $donation->update(['status' => DonationStatusEnum::REJECTED->value]);

            // Create RejectedLog with donation_id FK for strict audit trail
            // AGENTS.md §3: rejection MUST be logged in RejectedLog WITH donation_id
            RejectedLog::create([
                'donation_id' => $donation->id,
                'itemName'    => $donation->itemDonations->first()?->itemName_snapshot ?? 'Unknown',
                'reason'      => $reason,
                'logged_by'   => $loggedBy,
            ]);

            return $donation->fresh()->toArray();
        });
    }

    /**
     * Retrieve all distribution records for the dashboard history view.
     * Eager loads the associated Inventory item and the User who logged the entry.
     *
     * AGENTS.md §3: target_recipient and notes are mandatory audit fields;
     * they are present on every Distribution row.
     *
     * @return array  All distributions ordered newest-first.
     */
    public function getAllDistributions(): array
    {
        return Distribution::with(['inventory', 'user'])
            ->orderBy('distributed_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Record a distribution with atomic stock deduction.
     * Enforces auditability by requiring target_recipient and notes.
     *
     * UML Ref: Sequence Diagram §SD-7 — Distribution
     *   1. BEGIN TRANSACTION
     *   2. checkStock(item_id) via lockForUpdate()
     *   3. If current_stock < qty → ROLLBACK, throw InsufficientStock (HTTP 422)
     *   4. If valid, deduct stock + create Distribution record, COMMIT
     *
     * AGENTS.md §3: Distribution MUST explicitly record target_recipient and notes.
     *
     * @param array $data  ['inventory_id', 'user_id', 'qty', 'target_recipient', 'notes']
     * @return array       Serializable distribution data.
     */
    public function recordDistribution(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Pessimistic locking on inventory row
            $inventory = Inventory::where('id', $data['inventory_id'])
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                throw new HttpException(404, 'Inventory item not found.');
            }

            if ($inventory->stock < $data['qty']) {
                throw new HttpException(422, 'Stok tidak mencukupi untuk distribusi.');
            }

            // Core atomic deduction
            $inventory->decrement('stock', $data['qty']);

            // Create distribution record WITH mandatory auditability fields
            $distribution = Distribution::create([
                'inventory_id'     => $data['inventory_id'],
                'user_id'          => $data['user_id'],
                'qty'              => $data['qty'],
                'target_recipient' => $data['target_recipient'],
                'notes'            => $data['notes'] ?? null,
                'distributed_at'   => now(),
            ]);

            return $distribution->toArray();
        });
    }

    /**
     * Retrieve all inventory items for the public Smart Cart dropdown.
     * Returns only the fields needed for the frontend selector.
     *
     * @return array  List of inventory items as arrays.
     */
    public function getPublicInventoryList(): array
    {
        return Inventory::orderBy('category')
            ->orderBy('itemName')
            ->get()
            ->toArray();
    }

    /**
     * Retrieve all inventory catalog items for the Kelola Kebutuhan dashboard.
     * Returns the full row so the frontend can display progress (stock vs target_qty).
     *
     * @return array  List of all inventory items as arrays.
     */
    public function getAllInventories(array $filters = []): array
    {
        $query = Inventory::query()
            ->withSum(['itemDonations as virtual_stock' => function ($q) {
                $q->whereHas('donation', function ($d) {
                    $d->where('status', DonationStatusEnum::PENDING_DELIVERY->value)
                      ->where('expires_at', '>', now());
                });
            }], 'qty')
            ->withSum(['itemDonations as terkumpul_bulan_ini' => function ($q) {
                $q->whereHas('donation', function ($d) {
                    $d->where('status', DonationStatusEnum::SUCCESS->value);
                })->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
            }], 'qty');

        if (!empty($filters['search'])) {
            $query->where('itemName', 'ilike', '%' . $filters['search'] . '%');
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        $items = $query->orderBy('category')->orderBy('itemName')->get();

        // Cast withSum results and append virtual attributes
        $items->each(function ($item) {
            $item->virtual_stock = (int) $item->virtual_stock;
            $item->terkumpul_bulan_ini = (int) $item->terkumpul_bulan_ini;
            $item->append(['status_kebutuhan', 'is_disabled', 'next_available_date']);
        });

        if (!empty($filters['status_kebutuhan'])) {
            $items = $items->filter(function($item) use ($filters) {
                return $item->status_kebutuhan === $filters['status_kebutuhan'];
            });
        }

        return $items->values()->toArray();
    }

    /**
     * Create a new inventory catalog item.
     * stock MUST NOT be supplied here — it is managed exclusively by checkInItem().
     *
     * @param array $data  ['itemName', 'category', 'target_qty', 'unit', 'description']
     * @return Inventory
     */
    public function createInventory(array $data): Inventory
    {
        return DB::transaction(function () use ($data) {
            return Inventory::create([
                'itemName'    => $data['itemName'],
                'category'    => $data['category'],
                'priority'    => $data['priority'] ?? 'OPSIONAL',
                'target_qty'  => $data['target_qty'],
                'unit'        => $data['unit'],
                'description' => $data['description'] ?? null,
                // stock is intentionally omitted — defaults to 0 via migration
            ]);
        });
    }

    /**
     * Update an existing inventory catalog item.
     * stock MUST NOT be updated through this method.
     *
     * @param string $id    UUID of the inventory item.
     * @param array  $data  Partial or full update payload.
     * @return Inventory
     */
    public function updateInventory(string $id, array $data): Inventory
    {
        return DB::transaction(function () use ($id, $data) {
            $inventory = Inventory::findOrFail($id);

            // Invariant guard: target_qty must never fall below the already-collected stock.
            // Use array_key_exists (not isset) so an explicit null in $data is also caught.
            if (array_key_exists('target_qty', $data) && $data['target_qty'] < $inventory->stock) {
                throw new HttpException(
                    422,
                    'Target kuantitas tidak boleh lebih kecil dari stok yang sudah terkumpul.'
                );
            }

            // Explicitly whitelist updatable fields — stock is excluded
            $inventory->update(array_filter([
                'itemName'    => $data['itemName']    ?? null,
                'category'    => $data['category']    ?? null,
                'priority'    => $data['priority']    ?? null,
                'target_qty'  => $data['target_qty']  ?? null,
                'unit'        => $data['unit']         ?? null,
                'description' => $data['description']  ?? null,
            ], fn($v) => $v !== null));

            return $inventory->fresh();
        });
    }

    /**
     * Delete an inventory catalog item.
     * Protected by findOrFail to return 404 instead of silent no-op.
     *
     * @param string $id  UUID of the inventory item.
     * @return void
     */
    public function deleteInventory(string $id): void
    {
        DB::transaction(function () use ($id) {
            $inventory = Inventory::findOrFail($id);
            $inventory->delete();
        });
    }

    /**
     * Dispatch an acceptance notification to the donor asynchronously (fire-and-forget).
     * Triggered after a successful item check-in.
     *
     * UML Ref: §SD-4 step 6 — sendAcceptanceNotification(donor_email, wa, donation_id)
     */
    private function dispatchAcceptanceNotification(Donation $donation): void
    {
        try {
            Mail::raw(
                "Donasi barang Anda dengan kode {$donation->tracking_code} telah diterima dan diverifikasi oleh Panti Asuhan Empanti. "
                . "Terima kasih atas kebaikan Anda!",
                function ($message) use ($donation) {
                    $message->to($donation->donorEmail)
                            ->subject('Donasi Barang Diterima - Empanti SIMDK');
                }
            );
        } catch (\Throwable $e) {
            // Fire-and-forget: log failure but do not block the check-in flow
            Log::warning("Failed to dispatch acceptance notification for donation {$donation->id}: " . $e->getMessage());
        }
    }
}
