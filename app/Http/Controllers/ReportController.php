<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportParamsRequest;
use App\Services\ReportService;
use App\Enums\RoleEnum;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    /**
     * GET /api/reports
     * Generates operational report data for PENGURUS_PANTI or KEPALA_PANTI.
     *
     * Query Params:
     *   - start_date (nullable|date)
     *   - end_date   (nullable|date|after_or_equal:start_date)
     *   - type       (nullable|in:donation,visit,distribution)
     *   - export     (nullable|boolean) — if true, streams a PDF file
     *
     * Role Gate: PENGURUS_PANTI | KEPALA_PANTI
     */
    public function requestReport(ReportParamsRequest $request)
    {
        $role = Auth::user()->role;
        $roleValue = $role instanceof \BackedEnum ? $role->value : $role;

        if (!in_array($roleValue, [RoleEnum::PENGURUS_PANTI->value, RoleEnum::KEPALA_PANTI->value], true)) {
            return response()->json([
                'status'  => 'error',
                'code'    => 403,
                'message' => 'Forbidden Access.',
            ], 403);
        }

        $params = $request->validated();
        $data = $this->reportService->generateReport($params);

        // ── JSON Preview Mode ──────────────────────────────────
        if (!filter_var($params['export'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'status' => 'success',
                'data'   => $data,
            ]);
        }

        // ── PDF Export Mode ────────────────────────────────────
        $generatedBy = Auth::user()->name ?? null;

        $pdf = Pdf::loadView('reports.laporan-operasional', [
            'data'        => $data,
            'generatedBy' => $generatedBy,
        ])
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        $start = Carbon::parse($data['period']['start'] ?? now())->format('Y-m');
        $filename = "laporan-simdk-{$start}.pdf";

        return $pdf->download($filename);
    }
}
