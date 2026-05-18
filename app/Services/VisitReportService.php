<?php

namespace App\Services;

use App\Models\VisitReport;
use App\Models\Visit;
use App\Models\User;
use App\Enums\VisitStatusEnum;
use App\Enums\ReportStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VisitReportService
{
    /**
     * Submit a new visit report (Visitor context).
     *
     * Business Rules:
     *  1. The Visit must have status = COMPLETED.
     *  2. The User must be the owner of the Visit.
     *  3. Only one report per visit per user (enforced by DB unique constraint).
     *  4. Images are stored to the public disk under 'reports/{visit_id}/'.
     *  5. Report defaults to status = PENDING (Two-Step Moderation).
     *
     * @param array          $data       Validated request data (content, visit_id)
     * @param UploadedFile[] $imageFiles Array of uploaded image files (validated by FormRequest)
     * @param User           $user       The authenticated visitor
     * @return VisitReport
     */
    public function submitReport(array $data, array $imageFiles, User $user): VisitReport
    {
        $visit = Visit::findOrFail($data['visit_id']);

        // Constraint: Visit must belong to the authenticated user
        if ($visit->user_id !== $user->id) {
            throw new HttpException(403, 'Anda tidak memiliki akses untuk membuat laporan pada kunjungan ini.');
        }

        // Constraint: Visit must be COMPLETED
        $visitStatus = $visit->status instanceof VisitStatusEnum
            ? $visit->status->value
            : $visit->status;

        if ($visitStatus !== VisitStatusEnum::COMPLETED->value) {
            throw new HttpException(422, 'Laporan hanya dapat dibuat untuk kunjungan yang telah selesai (COMPLETED).');
        }

        // Constraint: One report per visit per user (fail early before DB unique constraint)
        $existingReport = VisitReport::where('visit_id', $visit->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingReport) {
            throw new HttpException(409, 'Anda sudah pernah membuat laporan untuk kunjungan ini.');
        }

        return DB::transaction(function () use ($data, $imageFiles, $user, $visit) {
            // Store images to public disk
            $imagePaths = [];
            foreach ($imageFiles as $file) {
                $path = $file->store("reports/{$visit->id}", 'public');
                $imagePaths[] = $path;
            }

            $report = VisitReport::create([
                'visit_id'   => $visit->id,
                'user_id'    => $user->id,
                'content'    => strip_tags($data['content']), // Prevent XSS
                'image_path' => !empty($imagePaths) ? $imagePaths : null,
                'status'     => ReportStatusEnum::PENDING->value,
            ]);

            return $report;
        });
    }

    /**
     * Moderate a visit report (Admin/Pengurus context).
     *
     * Transitions the report status from PENDING to PUBLISHED or REJECTED.
     * Optionally stores admin notes (e.g., rejection reason).
     *
     * @param VisitReport $report     The report to moderate
     * @param string      $status     Target status (PUBLISHED or REJECTED)
     * @param string|null $adminNotes Optional moderation notes
     * @return VisitReport
     */
    public function moderateReport(VisitReport $report, string $status, ?string $adminNotes = null, ?string $adminTitle = null): VisitReport
    {
        // Prevent re-moderation of already-finalized reports
        $currentStatus = $report->status instanceof ReportStatusEnum
            ? $report->status->value
            : $report->status;

        if ($currentStatus !== ReportStatusEnum::PENDING->value) {
            throw new HttpException(422, 'Laporan ini sudah dimoderasi sebelumnya.');
        }

        return DB::transaction(function () use ($report, $status, $adminNotes, $adminTitle) {
            $report->update([
                'status'      => $status,
                'admin_notes' => $adminNotes,
                'admin_title' => $adminTitle,
            ]);

            return $report->fresh();
        });
    }

    /**
     * Get all reports for admin moderation (paginated).
     *
     * @param string|null $statusFilter Optional status filter
     * @param int         $perPage      Items per page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getReportsForModeration(?string $statusFilter = null, int $perPage = 15)
    {
        $query = VisitReport::with(['user', 'visit.capacity'])
            ->latest('created_at');

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get published reports for public display (Transparency page).
     *
     * @param int $perPage Items per page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPublishedReports(int $perPage = 10)
    {
        return VisitReport::with(['user', 'visit.capacity'])
            ->where('status', ReportStatusEnum::PUBLISHED->value)
            ->latest('updated_at')
            ->paginate($perPage);
    }
}
