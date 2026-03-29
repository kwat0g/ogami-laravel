<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Services;

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
        return JobPosting::with(['requisition.department', 'requisition.position'])
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['job_requisition_id']), fn ($q) => $q->where('job_requisition_id', $filters['job_requisition_id']))
            ->when(isset($filters['search']), fn ($q) => $q->where('title', 'ILIKE', "%{$filters['search']}%"))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function show(JobPosting $posting): JobPosting
    {
        return $posting->load([
            'requisition.department',
            'requisition.position',
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

    public function reopen(JobPosting $posting, User $actor): JobPosting
    {
        if (! $posting->status->canTransitionTo(PostingStatus::Published)) {
            throw new DomainException(
                'Cannot reopen this posting.',
                'INVALID_STATUS_TRANSITION',
                422,
                ['current_status' => $posting->status->value],
            );
        }

        return DB::transaction(function () use ($posting): JobPosting {
            $posting->status = PostingStatus::Published;
            $posting->published_at = now();
            $posting->closes_at = null; // Reset expiry
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
