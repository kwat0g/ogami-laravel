<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

final class RecruitmentReportService implements ServiceContract
{
    /**
     * Pipeline report: count of applications at each stage per requisition.
     */
    public function pipeline(array $filters = []): array
    {
        $query = DB::table('applications')
            ->join('job_postings', 'applications.job_posting_id', '=', 'job_postings.id')
            ->join('job_requisitions', 'job_postings.job_requisition_id', '=', 'job_requisitions.id')
            ->join('departments', 'job_requisitions.department_id', '=', 'departments.id')
            ->join('positions', 'job_requisitions.position_id', '=', 'positions.id')
            ->whereNull('applications.deleted_at')
            ->select(
                'job_requisitions.requisition_number',
                'departments.name as department',
                'positions.title as position',
                DB::raw("COUNT(*) as total_applications"),
                DB::raw("COUNT(*) FILTER (WHERE applications.status = 'new') as new_count"),
                DB::raw("COUNT(*) FILTER (WHERE applications.status = 'under_review') as under_review_count"),
                DB::raw("COUNT(*) FILTER (WHERE applications.status = 'shortlisted') as shortlisted_count"),
                DB::raw("COUNT(*) FILTER (WHERE applications.status = 'rejected') as rejected_count"),
                DB::raw("COUNT(*) FILTER (WHERE applications.status = 'withdrawn') as withdrawn_count"),
            )
            ->groupBy('job_requisitions.requisition_number', 'departments.name', 'positions.title');

        if (isset($filters['department_id'])) {
            $query->where('job_requisitions.department_id', $filters['department_id']);
        }

        return $query->orderBy('job_requisitions.requisition_number')->get()->toArray();
    }

    /**
     * Time-to-fill: days from requisition approval to hire.
     */
    public function timeToFill(array $filters = []): array
    {
        $query = DB::table('hirings')
            ->join('job_requisitions', 'hirings.job_requisition_id', '=', 'job_requisitions.id')
            ->join('departments', 'job_requisitions.department_id', '=', 'departments.id')
            ->join('positions', 'job_requisitions.position_id', '=', 'positions.id')
            ->where('hirings.status', 'hired')
            ->whereNotNull('job_requisitions.approved_at')
            ->select(
                'job_requisitions.requisition_number',
                'departments.name as department',
                'positions.title as position',
                'job_requisitions.approved_at',
                'hirings.hired_at',
                DB::raw("EXTRACT(DAY FROM hirings.hired_at - job_requisitions.approved_at) as days_to_fill"),
            );

        if (isset($filters['department_id'])) {
            $query->where('job_requisitions.department_id', $filters['department_id']);
        }

        $rows = $query->orderBy('hirings.hired_at', 'desc')->get()->toArray();

        $avgDays = count($rows) > 0
            ? round(array_sum(array_column($rows, 'days_to_fill')) / count($rows), 1)
            : 0;

        return [
            'average_days' => $avgDays,
            'details' => $rows,
        ];
    }

    /**
     * Source mix: application count by source.
     */
    public function sourceMix(array $filters = []): array
    {
        $query = DB::table('applications')
            ->whereNull('deleted_at')
            ->select('source', DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->orderByDesc('count');

        if (isset($filters['from_date'])) {
            $query->where('application_date', '>=', $filters['from_date']);
        }
        if (isset($filters['to_date'])) {
            $query->where('application_date', '<=', $filters['to_date']);
        }

        return $query->get()->toArray();
    }
}
