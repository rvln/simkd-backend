<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Donation;
use App\Models\Visit;
use App\Models\Distribution;
use App\Enums\DonationStatusEnum;
use App\Enums\DonationTypeEnum;
use App\Enums\VisitStatusEnum;

class ReportService
{
    /**
     * Generate the full report dataset for a given period and optional type filter.
     *
     * @param array $params  Validated params: start_date, end_date, type, export
     * @return array         Structured dataset for PDF or JSON response.
     */
    public function generateReport(array $params): array
    {
        $start = isset($params['start_date'])
            ? Carbon::parse($params['start_date'])->startOfDay()
            : Carbon::now()->startOfMonth();

        $end = isset($params['end_date'])
            ? Carbon::parse($params['end_date'])->endOfDay()
            : Carbon::now()->endOfDay();

        $type = $params['type'] ?? null; // null = all sections

        $data = [
            'period'        => [
                'start'     => $start->format('d M Y'),
                'end'       => $end->format('d M Y'),
                'month'     => $start->locale('id')->translatedFormat('F Y'),
            ],
            'generated_at'  => Carbon::now()->format('d M Y, H:i'),
            'donations'     => null,
            'visits'        => null,
            'distributions' => null,
        ];

        if (!$type || $type === 'donation') {
            $data['donations'] = $this->queryDonations($start, $end);
        }

        if (!$type || $type === 'visit') {
            $data['visits'] = $this->queryVisits($start, $end);
        }

        if (!$type || $type === 'distribution') {
            $data['distributions'] = $this->queryDistributions($start, $end);
        }

        return $data;
    }

    /**
     * Aggregate donation data for the given period.
     */
    private function queryDonations(Carbon $start, Carbon $end): array
    {
        // Financial (DANA)
        $danaBase = Donation::where('type', DonationTypeEnum::DANA->value)
            ->whereBetween('created_at', [$start, $end]);

        $totalDanaAmount = (clone $danaBase)
            ->where('status', DonationStatusEnum::SUCCESS->value)
            ->sum('amount');

        $danaCountByStatus = (clone $danaBase)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Physical (BARANG)
        $barangBase = Donation::where('type', DonationTypeEnum::BARANG->value)
            ->whereBetween('created_at', [$start, $end]);

        $barangCountByStatus = (clone $barangBase)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'dana' => [
                'total_success_amount' => (float) $totalDanaAmount,
                'count_by_status'      => $danaCountByStatus,
                'total'                => array_sum($danaCountByStatus),
            ],
            'barang' => [
                'count_by_status' => $barangCountByStatus,
                'total'           => array_sum($barangCountByStatus),
            ],
        ];
    }

    /**
     * Aggregate visit data for the given period.
     */
    private function queryVisits(Carbon $start, Carbon $end): array
    {
        $countByStatus = Visit::whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'count_by_status' => $countByStatus,
            'total'           => array_sum($countByStatus),
        ];
    }

    /**
     * Fetch distribution records for the audit table in the report.
     */
    private function queryDistributions(Carbon $start, Carbon $end): array
    {
        $records = Distribution::with(['inventory', 'user'])
            ->whereBetween('distributed_at', [$start, $end])
            ->orderBy('distributed_at', 'desc')
            ->get()
            ->map(fn ($d) => [
                'item_name'        => $d->inventory?->itemName ?? 'N/A',
                'unit'             => $d->inventory?->unit ?? 'pcs',
                'qty'              => $d->qty,
                'target_recipient' => $d->target_recipient ?? '-',
                'notes'            => $d->notes ?? '-',
                'distributed_by'   => $d->user?->name ?? 'Admin',
                'distributed_at'   => Carbon::parse($d->distributed_at)->format('d M Y, H:i'),
            ])
            ->toArray();

        return [
            'records' => $records,
            'total'   => count($records),
        ];
    }
}
