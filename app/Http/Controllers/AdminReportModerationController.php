<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModerateReportRequest;
use App\Models\VisitReport;
use App\Services\VisitReportService;
use App\Enums\RoleEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AdminReportModerationController — Pengurus Panti Context
 *
 * Handles listing and moderating visit reports.
 * Zero business logic — delegates to VisitReportService.
 */
class AdminReportModerationController extends Controller
{
    public function __construct(private VisitReportService $visitReportService) {}

    /**
     * Enforce staff role (PENGURUS_PANTI or KEPALA_PANTI).
     */
    private function authorizeStaffRole(Request $request): void
    {
        $userRole = $request->user()?->role;
        $roleValue = $userRole instanceof RoleEnum ? $userRole->value : $userRole;

        if (!in_array($roleValue, [RoleEnum::PENGURUS_PANTI->value, RoleEnum::KEPALA_PANTI->value], true)) {
            abort(403, 'Akses ditolak. Hanya pengurus atau kepala panti yang dapat memoderasi laporan.');
        }
    }

    /**
     * GET /api/admin/visit-reports
     *
     * List all reports for moderation. Optionally filterable by status.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeStaffRole($request);

        $statusFilter = $request->input('status');
        $perPage = min((int) $request->input('per_page', 15), 50);

        $paginated = $this->visitReportService->getReportsForModeration($statusFilter, $perPage);

        $items = collect($paginated->items())->map(function (VisitReport $report) {
            return [
                'id'          => $report->id,
                'visitor'     => $report->user?->name ?? 'Pengunjung',
                'content'     => $report->content,
                'image_path'  => $report->image_path,
                'status'      => $report->status instanceof \App\Enums\ReportStatusEnum
                    ? $report->status->value
                    : $report->status,
                'admin_notes' => $report->admin_notes,
                'admin_title' => $report->admin_title,
                'visit_date'  => $report->visit?->capacity?->date?->toDateString(),
                'created_at'  => $report->created_at->toIso8601String(),
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
     * PATCH /api/admin/visit-reports/{id}/moderate
     *
     * Moderate a report (PUBLISHED or REJECTED).
     */
    public function moderate(ModerateReportRequest $request, string $id): JsonResponse
    {
        $this->authorizeStaffRole($request);

        try {
            $report = VisitReport::findOrFail($id);

            $updated = $this->visitReportService->moderateReport(
                $report,
                $request->validated()['status'],
                $request->validated()['admin_notes'] ?? null,
                $request->validated()['admin_title'] ?? null
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Laporan berhasil dimoderasi.',
                'data'    => $updated,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Laporan tidak ditemukan.',
            ], 404);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
