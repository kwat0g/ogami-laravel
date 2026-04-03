<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Services;

use App\Domains\HR\Recruitment\Enums\ApplicationStatus;
use App\Domains\HR\Recruitment\Enums\InterviewStatus;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\InterviewEvaluation;
use App\Domains\HR\Recruitment\Models\InterviewSchedule;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class InterviewService implements ServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<InterviewSchedule>
     */
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return InterviewSchedule::with(['application.candidate', 'application.posting.requisition.position', 'interviewer', 'interviewerDepartment', 'evaluation'])
            ->when(isset($filters['application_id']), fn ($q) => $q->where('application_id', $filters['application_id']))
            ->when(isset($filters['interviewer_id']), fn ($q) => $q->where('interviewer_id', $filters['interviewer_id']))
            ->when(isset($filters['interviewer_department_id']), fn ($q) => $q->where('interviewer_department_id', $filters['interviewer_department_id']))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['from_date']), fn ($q) => $q->where('scheduled_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn ($q) => $q->where('scheduled_at', '<=', $filters['to_date']))
            ->orderBy('scheduled_at')
            ->paginate($perPage);
    }

    public function show(InterviewSchedule $interview): InterviewSchedule
    {
        return $interview->load([
            'application.candidate',
            'application.posting.requisition.position',
            'interviewer',
            'evaluation.submitter',
        ]);
    }

    public function schedule(Application $application, array $data, User $actor): InterviewSchedule
    {
        if (! in_array($application->status, [ApplicationStatus::Shortlisted, ApplicationStatus::UnderReview])) {
            throw new DomainException(
                'Can only schedule interviews for shortlisted or under-review applications.',
                'APPLICATION_NOT_SHORTLISTED',
                422,
                ['current_status' => $application->status->value],
            );
        }

        return DB::transaction(function () use ($application, $data): InterviewSchedule {
            $round = $data['round'] ?? ($application->interviews()->max('round') ?? 0) + 1;

            return InterviewSchedule::create([
                'application_id' => $application->id,
                'round' => $round,
                'type' => $data['type'],
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $data['duration_minutes'] ?? 60,
                'location' => $data['location'] ?? null,
                'interviewer_id' => $data['interviewer_id'] ?? null,
                'interviewer_department_id' => $data['interviewer_department_id'] ?? null,
                'status' => InterviewStatus::Scheduled->value,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function reschedule(InterviewSchedule $interview, array $data, User $actor): InterviewSchedule
    {
        if ($interview->status !== InterviewStatus::Scheduled) {
            throw new DomainException(
                'Can only reschedule a scheduled interview.',
                'INTERVIEW_NOT_RESCHEDULABLE',
                422,
                ['current_status' => $interview->status->value],
            );
        }

        return DB::transaction(function () use ($interview, $data): InterviewSchedule {
            $interview->update([
                'scheduled_at' => $data['scheduled_at'] ?? $interview->scheduled_at,
                'duration_minutes' => $data['duration_minutes'] ?? $interview->duration_minutes,
                'location' => $data['location'] ?? $interview->location,
                'interviewer_id' => $data['interviewer_id'] ?? $interview->interviewer_id,
                'interviewer_department_id' => $data['interviewer_department_id'] ?? $interview->interviewer_department_id,
                'notes' => $data['notes'] ?? $interview->notes,
            ]);

            return $interview->fresh();
        });
    }

    public function cancel(InterviewSchedule $interview, User $actor, ?string $reason = null): InterviewSchedule
    {
        if (! $interview->status->canTransitionTo(InterviewStatus::Cancelled)) {
            throw new DomainException(
                'Cannot cancel this interview.',
                'INVALID_STATUS_TRANSITION',
                422,
                ['current_status' => $interview->status->value],
            );
        }

        return DB::transaction(function () use ($interview, $reason): InterviewSchedule {
            $interview->status = InterviewStatus::Cancelled;
            $interview->notes = $reason ? ($interview->notes ? "{$interview->notes}\nCancelled: {$reason}" : "Cancelled: {$reason}") : $interview->notes;
            $interview->save();

            return $interview;
        });
    }

    public function markNoShow(InterviewSchedule $interview, User $actor): InterviewSchedule
    {
        if (! $interview->status->canTransitionTo(InterviewStatus::NoShow)) {
            throw new DomainException(
                'Cannot mark no-show for this interview.',
                'INVALID_STATUS_TRANSITION',
                422,
                ['current_status' => $interview->status->value],
            );
        }

        return DB::transaction(function () use ($interview): InterviewSchedule {
            $interview->status = InterviewStatus::NoShow;
            $interview->save();

            return $interview;
        });
    }

    public function complete(InterviewSchedule $interview, User $actor): InterviewSchedule
    {
        if (! $interview->status->canTransitionTo(InterviewStatus::Completed)) {
            // Allow direct scheduled -> completed when evaluation is submitted
            if ($interview->status === InterviewStatus::Scheduled) {
                $interview->status = InterviewStatus::Completed;
                $interview->save();

                return $interview;
            }

            throw new DomainException(
                'Cannot complete this interview.',
                'INVALID_STATUS_TRANSITION',
                422,
                ['current_status' => $interview->status->value],
            );
        }

        return DB::transaction(function () use ($interview): InterviewSchedule {
            $interview->status = InterviewStatus::Completed;
            $interview->save();

            return $interview;
        });
    }

    public function submitEvaluation(InterviewSchedule $interview, array $data, User $actor): InterviewEvaluation
    {
        if ($interview->evaluation()->exists()) {
            throw new DomainException(
                'An evaluation has already been submitted for this interview.',
                'EVALUATION_ALREADY_EXISTS',
                422,
                ['interview_id' => $interview->id],
            );
        }

        if (! in_array($interview->status, [InterviewStatus::Scheduled, InterviewStatus::InProgress, InterviewStatus::Completed])) {
            throw new DomainException(
                'Cannot submit evaluation for a cancelled or no-show interview.',
                'INTERVIEW_NOT_EVALUABLE',
                422,
                ['current_status' => $interview->status->value],
            );
        }

        return DB::transaction(function () use ($interview, $data, $actor): InterviewEvaluation {
            // Auto-complete interview if not already
            if ($interview->status !== InterviewStatus::Completed) {
                $interview->status = InterviewStatus::Completed;
                $interview->save();
            }

            // Calculate overall score
            $scorecard = $data['scorecard'];
            $scores = array_column($scorecard, 'score');
            $overallScore = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;

            return InterviewEvaluation::create([
                'interview_schedule_id' => $interview->id,
                'submitted_by' => $actor->id,
                'scorecard' => $scorecard,
                'overall_score' => $overallScore,
                'recommendation' => $data['recommendation'],
                'general_remarks' => $data['general_remarks'] ?? null,
                'submitted_at' => now(),
            ]);
        });
    }
}
