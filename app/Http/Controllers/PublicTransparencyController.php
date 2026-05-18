<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\Distribution;
use App\Models\Inventory;
use App\Models\Visit;
use App\Enums\DonationStatusEnum;
use App\Enums\DonationTypeEnum;
use App\Enums\VisitStatusEnum;
use App\Enums\ReportStatusEnum;
use App\Models\VisitReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PublicTransparencyController
 *
 * Serves READ-ONLY, PII-masked data for the public Transparency page.
 * No business logic lives here — only query + response shaping.
 *
 * PII Protection:
 *  - Donor emails and phone numbers are NEVER exposed.
 *  - Donor names are masked (e.g., "Budi Santoso" → "B***i S.")
 */
class PublicTransparencyController extends Controller
{
    /**
     * GET /api/public/transparansi/donasi
     *
     * Returns a paginated list of successful/validated donations
     * with PII-masked donor names. Filterable by type (DANA/BARANG).
     */
    public function donations(Request $request): JsonResponse
    {
        $query = Donation::query()
            ->whereIn('status', [
                DonationStatusEnum::SUCCESS,
            ])
            ->latest('updated_at');

        // Optional type filter: ?type=DANA or ?type=BARANG
        if ($request->filled('type') && in_array(strtoupper($request->input('type')), ['DANA', 'BARANG'], true)) {
            $query->where('type', strtoupper($request->input('type')));
        }

        $paginated = $query->paginate(
            perPage: min((int) $request->input('per_page', 10), 25),
            page: (int) $request->input('page', 1)
        );

        $items = collect($paginated->items())->map(function (Donation $donation) {
            $maskedName = $this->maskName($donation->donorName);
            $typeValue = $donation->type instanceof DonationTypeEnum
                ? $donation->type->value
                : $donation->type;
            $statusValue = $donation->status instanceof DonationStatusEnum
                ? $donation->status->value
                : $donation->status;

            $base = [
                'id'          => $donation->id,
                'masked_name' => $maskedName,
                'type'        => $typeValue,
                'status'      => $statusValue,
                'created_at'  => $donation->created_at->toIso8601String(),
                'updated_at'  => $donation->updated_at->toIso8601String(),
            ];

            // For DANA donations, include amount (no item details)
            if ($typeValue === 'DANA') {
                $base['amount'] = $donation->amount;
            }

            // For BARANG donations, load item names via relationship
            if ($typeValue === 'BARANG') {
                $donation->loadMissing('itemDonations.inventory');
                $base['items'] = $donation->itemDonations->map(fn ($item) => [
                    'name' => $item->itemName_snapshot ?? $item->inventory?->itemName ?? 'Barang',
                    'qty'  => $item->qty,
                    'unit' => $item->inventory?->unit ?? 'pcs',
                ]);
            }

            return $base;
        });

        return response()->json([
            'status' => 'success',
            'data'   => $items,
            'meta'   => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/public/transparansi/distribusi
     *
     * Returns a paginated list of distributions for public audit.
     * No PII exposed — only item names, quantities, recipients, and dates.
     * Filterable by inventory category: ?category=MAKANAN
     */
    public function distributions(Request $request): JsonResponse
    {
        $query = Distribution::with(['inventory'])
            ->latest('distributed_at');

        // Optional category filter via inventory relationship
        if ($request->filled('category')) {
            $categoryValue = strtoupper($request->input('category'));
            $query->whereHas('inventory', function ($q) use ($categoryValue) {
                $q->where('category', $categoryValue);
            });
        }

        $paginated = $query->paginate(
            perPage: min((int) $request->input('per_page', 10), 25),
            page: (int) $request->input('page', 1)
        );

        $items = collect($paginated->items())->map(function (Distribution $dist) {
            $categoryValue = $dist->inventory?->category;
            if ($categoryValue instanceof \App\Enums\InventoryEnum) {
                $categoryValue = $categoryValue->value;
            }

            return [
                'id'               => $dist->id,
                'item_name'        => $dist->inventory?->itemName ?? 'Barang',
                'category'         => $categoryValue ?? 'LAINNYA',
                'qty'              => $dist->qty,
                'unit'             => $dist->inventory?->unit ?? 'pcs',
                'target_recipient' => $dist->target_recipient,
                'distributed_at'   => $dist->distributed_at->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $items,
            'meta'   => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/public/transparansi/kebutuhan
     *
     * Returns the operational needs (inventory) with progress data
     * for the transparency page's "Kebutuhan Operasional" section.
     * Filterable by category: ?category=MAKANAN
     */
    public function inventories(Request $request): JsonResponse
    {
        $query = Inventory::query();

        if ($request->filled('category')) {
            $query->where('category', strtoupper($request->input('category')));
        }

        // Return all items (not paginated — catalog is small)
        $items = $query->get()->map(function (Inventory $item) {
            $categoryValue = $item->category instanceof \App\Enums\InventoryEnum
                ? $item->category->value
                : $item->category;
            $priorityValue = $item->priority instanceof \App\Enums\PriorityEnum
                ? $item->priority->value
                : $item->priority;

            return [
                'id'          => $item->id,
                'name'        => $item->itemName,
                'description' => $item->description,
                'category'    => $categoryValue,
                'priority'    => $priorityValue,
                'stock'       => $item->stock,
                'target_qty'  => $item->target_qty,
                'unit'        => $item->unit,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $items,
        ]);
    }

    /**
     * GET /api/public/transparansi/kunjungan
     *
     * Returns completed & upcoming visits with PII-masked visitor names.
     * Used for the "Agenda & Kunjungan Terkini" section.
     */
    public function visits(Request $request): JsonResponse
    {
        $query = Visit::with(['user', 'capacity'])
            ->whereIn('status', [
                VisitStatusEnum::COMPLETED,
                VisitStatusEnum::APPROVED,
            ])
            ->latest('updated_at');

        $paginated = $query->paginate(
            perPage: min((int) $request->input('per_page', 6), 12),
            page: (int) $request->input('page', 1)
        );

        $items = collect($paginated->items())->map(function (Visit $visit) {
            $statusValue = $visit->status instanceof VisitStatusEnum
                ? $visit->status->value
                : $visit->status;

            return [
                'id'            => $visit->id,
                'visitor_name'  => $this->maskName($visit->user?->name ?? 'Pengunjung'),
                'status'        => $statusValue,
                'visit_date'    => $visit->capacity?->date?->toDateString(),
                'slot'          => $visit->capacity?->slot instanceof \App\Enums\TimeSlotEnum
                    ? $visit->capacity->slot->value
                    : $visit->capacity?->slot,
                'updated_at'    => $visit->updated_at->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $items,
            'meta'   => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/public/kunjungan/upcoming
     *
     * Returns upcoming visits (all statuses) for the public schedule page.
     * PII-masked visitor names. Used by the jadwal-kunjungan calendar & cards.
     */
    public function upcomingVisits(Request $request): JsonResponse
    {
        $query = Visit::with(['user', 'capacity'])
            ->whereHas('capacity', function ($q) {
                $q->where('date', '>=', now()->toDateString());
            })
            ->latest('created_at');

        $items = $query->get()->map(function (Visit $visit) {
            $statusValue = $visit->status instanceof VisitStatusEnum
                ? $visit->status->value
                : $visit->status;

            return [
                'id'            => $visit->id,
                'visitor_name'  => $this->maskName($visit->user?->name ?? 'Pengunjung'),
                'status'        => $statusValue,
                'visit_date'    => $visit->capacity?->date?->toDateString(),
                'slot'          => $visit->capacity?->slot instanceof \App\Enums\TimeSlotEnum
                    ? $visit->capacity->slot->value
                    : $visit->capacity?->slot,
                'created_at'    => $visit->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $items,
        ]);
    }

    /**
     * GET /api/public/transparansi/laporan
     *
     * Returns paginated PUBLISHED visit reports for public display.
     * PII-masked visitor names. Only status === PUBLISHED is returned.
     */
    public function visitReports(Request $request): JsonResponse
    {
        $query = VisitReport::with(['user', 'visit.capacity'])
            ->where('status', ReportStatusEnum::PUBLISHED->value)
            ->latest('updated_at');

        $paginated = $query->paginate(
            perPage: min((int) $request->input('per_page', 6), 12),
            page: (int) $request->input('page', 1)
        );

        $items = collect($paginated->items())->map(function (VisitReport $report) {
            // Derive title: prefer admin_title, fallback to first 6 words of content
            $derivedTitle = $report->admin_title
                ?? implode(' ', array_slice(explode(' ', $report->content ?? ''), 0, 6)) . '...';

            return [
                'id'           => $report->id,
                'visitor_name' => $this->maskName($report->user?->name ?? 'Pengunjung'),
                'title'        => $derivedTitle,
                'content'      => $report->content,
                'image_path'   => $report->image_path,
                'visit_date'   => $report->visit?->capacity?->date?->toDateString(),
                'created_at'   => $report->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $items,
            'meta'   => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * Mask a donor/visitor name for PII protection.
     *
     * "Budi Santoso" → "Bu**i S."
     * "Ahmad"        → "Ah***d"
     * null / empty   → "Hamba Allah"
     */
    private function maskName(?string $name): string
    {
        if (empty($name)) {
            return 'Hamba Allah';
        }

        $parts = explode(' ', trim($name));
        $masked = [];

        foreach ($parts as $i => $part) {
            $len = mb_strlen($part);

            if ($len <= 2) {
                $masked[] = $part[0] . '*';
            } elseif ($i > 0 && $i === count($parts) - 1) {
                // Last name: first letter + dot
                $masked[] = mb_substr($part, 0, 1) . '.';
            } else {
                // First/middle name: keep first 2, mask middle, keep last
                $maskLen = max(1, $len - 3);
                $masked[] = mb_substr($part, 0, 2) . str_repeat('*', $maskLen) . mb_substr($part, -1);
            }
        }

        return implode(' ', $masked);
    }

    /**
     * GET /api/public/jejak-kebaikan
     *
     * Returns the 3 most recent successful donations (DANA + BARANG) for the
     * landing page "Jejak Kebaikan" section.
     *
     * Name display respects donor_name_privacy:
     *  'show' → PII-masked name via maskName()
     *  'hide' → "Hamba Allah"
     *  'anon' → raw donorName (alias already stored at donation time)
     */
    public function jejak(): JsonResponse
    {
        $donations = Donation::whereIn('status', [DonationStatusEnum::SUCCESS->value])
            ->whereIn('type', [
                DonationTypeEnum::DANA->value,
                DonationTypeEnum::BARANG->value,
            ])
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(function (Donation $donation) {
                $privacy = $donation->donor_name_privacy ?? 'show';
                $typeVal = $donation->type instanceof DonationTypeEnum
                    ? $donation->type->value
                    : $donation->type;

                $displayName = match ($privacy) {
                    'hide'  => 'Hamba Allah',
                    'anon'  => $donation->donorName, // alias stored at submit time
                    default => $this->maskName($donation->donorName),
                };

                return [
                    'masked_name' => $displayName,
                    'type'        => $typeVal,
                    'amount'      => $typeVal === 'DANA' ? (float) $donation->amount : null,
                    'updated_at'  => $donation->updated_at->toIso8601String(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => $donations,
        ]);
    }
}
