<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Services;

use App\Domains\HR\Recruitment\Enums\ApplicationStatus;
use App\Domains\HR\Recruitment\Enums\OfferStatus;
use App\Domains\HR\Recruitment\Enums\RequisitionStatus;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\InterviewSchedule;
use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

final class RecruitmentDashboardService implements ServiceContract
{
    public function getKpis(): array
    {
        return [
            'open_requisitions' => JobRequisition::whereIn('status', [
                RequisitionStatus::Open->value,
                RequisitionStatus::Approved->value,
            ])->count(),

            'active_applications' => Application::whereNotIn('status', [
                ApplicationStatus::Rejected->value,
                ApplicationStatus::Withdrawn->value,
            ])->count(),

            'interviews_this_week' => InterviewSchedule::whereBetween('scheduled_at', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])->count(),

            'pending_offers' => JobOffer::where('status', OfferStatus::Sent->value)->count(),
        ];
    }

    public function getPipelineFunnel(): array
    {
        return [
            'requisitions' => JobRequisition::count(),
            'postings' => DB::table('job_postings')->whereNull('deleted_at')->count(),
            'applications' => Application::count(),
            'shortlisted' => Application::where('status', ApplicationStatus::Shortlisted->value)->count(),
            'interviewed' => DB::table('interview_schedules')
                ->where('status', 'completed')
                ->distinct('application_id')
                ->count('application_id'),
            'offered' => JobOffer::count(),
            'hired' => DB::table('hirings')->where('status', 'hired')->count(),
        ];
    }

    public function getSourceMix(): array
    {
        return Application::select('source', DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'source' => $row->source->value,
                'label' => $row->source->label(),
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    public function getRecentRequisitions(int $limit = 5): array
    {
        return JobRequisition::with(['department', 'position'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'ulid' => $r->ulid,
                'requisition_number' => $r->requisition_number,
                'department' => $r->department?->name,
                'position' => $r->position?->title,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'status_color' => $r->status->color(),
                'days_open' => $r->created_at->diffInDays(now()),
                'created_at' => $r->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    public function getUpcomingInterviews(int $limit = 5): array
    {
        return InterviewSchedule::with(['application.candidate', 'application.posting.requisition.position', 'interviewer'])
            ->where('scheduled_at', '>', now())
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'candidate_name' => $i->application->candidate->full_name,
                'position' => $i->application->posting->requisition->position->title ?? 'N/A',
                'interviewer' => $i->interviewer->name,
                'scheduled_at' => $i->scheduled_at->toIso8601String(),
                'type' => $i->type->label(),
                'round' => $i->round,
            ])
            ->toArray();
    }
}
