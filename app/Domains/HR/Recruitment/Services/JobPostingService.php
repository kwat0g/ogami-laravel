<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Services;

use App\Domains\HR\Recruitment\Enums\ApplicationStatus;
use App\Domains\HR\Recruitment\Enums\PostingStatus;
use App\Domains\HR\Recruitment\Enums\RequisitionStatus;
use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class JobPostingService implements ServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<JobPosting>
     */
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return JobPosting::with([
            'requisition.department',
            'requisition.position',
            'requisition.salaryGrade',
            'department',
            'position',
            'salaryGrade',
        ])
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['job_requisition_id']), fn ($q) => $q->where('job_requisition_id', $filters['job_requisition_id']))
            ->when(isset($filters['search']), fn ($q) => $q->where('title', 'ILIKE', "%{$filters['search']}%"))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /** @return LengthAwarePaginator<JobPosting> */
    public function listPublicActive(int $perPage = 20): LengthAwarePaginator
    {
        return JobPosting::with([
            'requisition.department',
            'requisition.position',
            'requisition.salaryGrade',
            'department',
            'position',
            'salaryGrade',
        ])
            ->published()
            ->where(function ($q) {
                $q->whereNull('closes_at')
                    ->orWhere('closes_at', '>', now());
            })
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    public function show(JobPosting $posting): JobPosting
    {
        return $posting->load([
            'requisition.department',
            'requisition.position',
            'requisition.salaryGrade',
            'department',
            'position',
            'salaryGrade',
            'applications.candidate',
        ]);
    }

    public function createFromRequisition(JobRequisition $requisition, array $data, User $actor): JobPosting
    {
        if (! in_array($requisition->status, [RequisitionStatus::Approved, RequisitionStatus::Open])) {
            throw new DomainException(
                'Can only create postings from approved or open requisitions.',
                'REQUISITION_NOT_APPROVED',
                422,
                ['current_status' => $requisition->status->value],
            );
        }

        return DB::transaction(function () use ($requisition, $data, $actor): JobPosting {
            $posting = JobPosting::create([
                ...$data,
                'job_requisition_id' => $requisition->id,
                'department_id' => $requisition->department_id,
                'position_id' => $requisition->position_id,
                'salary_grade_id' => $requisition->salary_grade_id,
                'headcount' => $requisition->headcount,
                'employment_type' => $data['employment_type'] ?? $requisition->employment_type->value,
                'status' => PostingStatus::Draft->value,
            ]);

            // Auto-open the requisition if still in approved status
            if ($requisition->status === RequisitionStatus::Approved) {
                $requisition->status = RequisitionStatus::Open;
                $requisition->save();
                $requisition->logApproval('opened', 'opened', $actor, 'Auto-opened when posting created');
            }

            return $posting;
        });
    }

    public function createDirect(array $data, User $actor): JobPosting
    {
        return DB::transaction(function () use ($data): JobPosting {
            return JobPosting::create([
                ...$data,
                'job_requisition_id' => null,
                'status' => PostingStatus::Draft->value,
            ]);
        });
    }

    public function update(JobPosting $posting, array $data): JobPosting
    {
        if (! in_array($posting->status, [PostingStatus::Draft, PostingStatus::Published])) {
            throw new DomainException(
                'Can only edit draft or published postings.',
                'POSTING_NOT_EDITABLE',
                422,
                ['current_status' => $posting->status->value],
            );
        }

        $hasActiveApplicants = $posting->applications()
            ->whereNotIn('status', [
                ApplicationStatus::Rejected->value,
                ApplicationStatus::Withdrawn->value,
            ])
            ->exists();

        if ($hasActiveApplicants) {
            throw new DomainException(
                'Cannot edit posting while it has active applicants.',
                'POSTING_HAS_ACTIVE_APPLICANTS',
                422,
                ['posting_ulid' => $posting->ulid],
            );
        }

        return DB::transaction(function () use ($posting, $data): JobPosting {
            $posting->update($data);

            return $posting->fresh();
        });
    }

    public function publish(JobPosting $posting, User $actor): JobPosting
    {
        if (! $posting->status->canTransitionTo(PostingStatus::Published)) {
            throw new DomainException(
                'Cannot publish this posting.',
                'INVALID_STATUS_TRANSITION',
                422,
                ['current_status' => $posting->status->value],
            );
        }

        return DB::transaction(function () use ($posting): JobPosting {
            $posting->status = PostingStatus::Published;
            $posting->published_at = now();
            $posting->save();

            return $posting;
        });
    }

    public function close(JobPosting $posting, User $actor): JobPosting
    {
        if (! $posting->status->canTransitionTo(PostingStatus::Closed)) {
            throw new DomainException(
                'Cannot close this posting.',
                'INVALID_STATUS_TRANSITION',
                422,
                ['current_status' => $posting->status->value],
            );
        }

        return DB::transaction(function () use ($posting): JobPosting {
            $posting->status = PostingStatus::Closed;
            $posting->save();

            return $posting;
        });
    }

    public function reopen(JobPosting $posting, User $actor, ?int $headcount = null): JobPosting
    {
        if (! $posting->status->canTransitionTo(PostingStatus::Published)) {
            throw new DomainException(
                'Cannot reopen this posting.',
                'INVALID_STATUS_TRANSITION',
                422,
                ['current_status' => $posting->status->value],
            );
        }

        if ($headcount !== null) {
            $hiredCount = $posting->applications()
                ->where('status', ApplicationStatus::Hired->value)
                ->count();

            if ($headcount < $hiredCount) {
                throw new DomainException(
                    'Headcount cannot be lower than already hired applicants.',
                    'POSTING_HEADCOUNT_BELOW_HIRED_COUNT',
                    422,
                    [
                        'headcount' => $headcount,
                        'already_hired' => $hiredCount,
                    ],
                );
            }
        }

        return DB::transaction(function () use ($posting, $headcount): JobPosting {
            $posting->status = PostingStatus::Published;
            $posting->published_at = now();
            $posting->closes_at = null; // Reset expiry
            if ($headcount !== null) {
                $posting->headcount = $headcount;
            }
            $posting->save();

            return $posting;
        });
    }

    public function expire(JobPosting $posting): JobPosting
    {
        if (! $posting->status->canTransitionTo(PostingStatus::Expired)) {
            return $posting;
        }

        return DB::transaction(function () use ($posting): JobPosting {
            $posting->status = PostingStatus::Expired;
            $posting->save();

            return $posting;
        });
    }
}
