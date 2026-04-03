<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Services;

use App\Domains\HR\Recruitment\Enums\ApplicationStatus;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\Candidate;
use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Domains\HR\Recruitment\StateMachines\ApplicationStateMachine;
use App\Models\User;
use App\Notifications\Recruitment\ApplicationReceivedNotification;
use App\Notifications\Recruitment\ApplicationRejectedNotification;
use App\Notifications\Recruitment\ApplicationShortlistedNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

final class ApplicationService implements ServiceContract
{
    public function __construct(
        private readonly ApplicationStateMachine $stateMachine,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Application>
     */
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Application::with(['candidate', 'posting.requisition.department', 'posting.requisition.position'])
            ->when(isset($filters['job_posting_id']), fn ($q) => $q->where('job_posting_id', $filters['job_posting_id']))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['candidate_id']), fn ($q) => $q->where('candidate_id', $filters['candidate_id']))
            ->when(isset($filters['search']), fn ($q) => $q->where(function ($s) use ($filters) {
                $s->where('application_number', 'ILIKE', "%{$filters['search']}%")
                    ->orWhereHas('candidate', fn ($c) => $c->whereRaw(
                        "LOWER(first_name || ' ' || last_name) LIKE ?",
                        ['%' . strtolower($filters['search']) . '%']
                    ));
            }))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function show(Application $application): Application
    {
        return $application->load([
            'candidate',
            'posting.requisition.department',
            'posting.requisition.position',
            'interviews.evaluation',
            'interviews.interviewer',
            'documents',
            'offer.offeredPosition',
            'offer.offeredDepartment',
            'preEmploymentChecklist.requirements',
            'hiring',
            'reviewer',
            'audits',
        ]);
    }

    public function apply(JobPosting $posting, array $candidateData, array $appData, ?UploadedFile $resume = null): Application
    {
        if (! $posting->isOpen()) {
            throw new DomainException(
                'This job posting is not accepting applications.',
                'POSTING_NOT_OPEN',
                422,
                ['posting_status' => $posting->status->value],
            );
        }

        $application = DB::transaction(function () use ($posting, $candidateData, $appData, $resume): Application {
            // Handle resume upload before creating candidate
            $resumePath = null;
            if ($resume) {
                $resumePath = $resume->store('recruitment/resumes', 'local');
            }

            // Find or create candidate by email
            $candidateCreateData = $candidateData;
            if ($resumePath) {
                $candidateCreateData['resume_path'] = $resumePath;
            }
            $candidate = Candidate::firstOrCreate(
                ['email' => $candidateData['email']],
                $candidateCreateData,
            );
            // Update resume path even if candidate already existed
            if ($resumePath && $candidate->resume_path !== $resumePath) {
                $candidate->update(['resume_path' => $resumePath]);
            }

            // Check duplicate application
            $exists = Application::where('job_posting_id', $posting->id)
                ->where('candidate_id', $candidate->id)
                ->exists();

            if ($exists) {
                throw new DomainException(
                    'This candidate has already applied to this posting.',
                    'DUPLICATE_APPLICATION',
                    422,
                    ['candidate_id' => $candidate->id, 'posting_id' => $posting->id],
                );
            }

            return Application::create([
                'job_posting_id' => $posting->id,
                'candidate_id' => $candidate->id,
                'cover_letter' => $appData['cover_letter'] ?? null,
                'application_date' => $appData['application_date'] ?? now()->toDateString(),
                'source' => $appData['source'] ?? $candidate->source?->value ?? 'walk_in',
                'status' => ApplicationStatus::New->value,
            ]);
        });

        // Notify HR recruiters about new application
        $hrUsers = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['manager', 'officer']))
            ->whereHas('departments', fn ($q) => $q->where('code', 'HR'))
            ->get();
        foreach ($hrUsers as $hr) {
            $hr->notify(ApplicationReceivedNotification::fromModel($application->load(['candidate', 'posting.requisition.position'])));
        }

        return $application;
    }

    public function review(Application $application, User $actor): Application
    {
        return DB::transaction(function () use ($application, $actor): Application {
            $this->stateMachine->transition($application, ApplicationStatus::UnderReview);
            $application->reviewed_by = $actor->id;
            $application->reviewed_at = now();
            $application->save();

            return $application;
        });
    }

    public function shortlist(Application $application, User $actor): Application
    {
        $result = DB::transaction(function () use ($application, $actor): Application {
            // Auto-review first if still new
            if ($application->status === ApplicationStatus::New) {
                $this->stateMachine->transition($application, ApplicationStatus::UnderReview);
                $application->reviewed_by = $actor->id;
                $application->reviewed_at = now();
                $application->save();
                $application->refresh();
            }

            $this->stateMachine->transition($application, ApplicationStatus::Shortlisted);
            $application->save();

            return $application;
        });

        // Notify candidate via on-demand mail (Candidate is not a User)
        $candidate = $result->candidate;
        if ($candidate?->email) {
            Notification::route('mail', $candidate->email)
                ->notify(ApplicationShortlistedNotification::fromModel($result->load(['posting'])));
        }

        return $result;
    }

    public function reject(Application $application, User $actor, string $reason): Application
    {
        $result = DB::transaction(function () use ($application, $actor, $reason): Application {
            $this->stateMachine->transition($application, ApplicationStatus::Rejected);
            $application->rejection_reason = $reason;
            $application->reviewed_by = $application->reviewed_by ?? $actor->id;
            $application->reviewed_at = $application->reviewed_at ?? now();
            $application->save();

            return $application;
        });

        // Notify candidate via on-demand mail
        $candidate = $result->candidate;
        if ($candidate?->email) {
            Notification::route('mail', $candidate->email)
                ->notify(ApplicationRejectedNotification::fromModel($result->load(['posting'])));
        }

        return $result;
    }

    public function withdraw(Application $application, string $reason): Application
    {
        return DB::transaction(function () use ($application, $reason): Application {
            $this->stateMachine->transition($application, ApplicationStatus::Withdrawn);
            $application->withdrawn_reason = $reason;
            $application->save();

            return $application;
        });
    }

    public function delete(Application $application): void
    {
        DB::transaction(function () use ($application): void {
            $application->delete();
        });
    }
}
