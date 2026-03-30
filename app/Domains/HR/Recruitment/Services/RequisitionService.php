<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Services;

use App\Domains\HR\Recruitment\Enums\RequisitionStatus;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Domains\HR\Recruitment\StateMachines\RequisitionStateMachine;
use App\Models\User;
use App\Notifications\Recruitment\RequisitionDecidedNotification;
use App\Notifications\Recruitment\RequisitionSubmittedNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class RequisitionService implements ServiceContract
{
    public function __construct(
        private readonly RequisitionStateMachine $stateMachine,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<JobRequisition>
     */
    public function list(array $filters = [], int $perPage = 25, ?User $scopedUser = null): LengthAwarePaginator
    {
        return JobRequisition::with(['department', 'position', 'requester'])
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->when(isset($filters['requested_by']), fn ($q) => $q->where('requested_by', $filters['requested_by']))
            // Department-scoped: non-HR users only see their department's requisitions
            ->when($scopedUser && ! $scopedUser->hasPermissionTo('hr.full_access'), function ($q) use ($scopedUser) {
                $deptIds = $scopedUser->departments->pluck('id')->toArray();
                if (! empty($deptIds)) {
                    $q->where(fn ($sub) => $sub->whereIn('department_id', $deptIds)->orWhere('requested_by', $scopedUser->id));
                }
            })
            ->when(isset($filters['search']), fn ($q) => $q->where(function ($s) use ($filters) {
                $s->where('requisition_number', 'ILIKE', "%{$filters['search']}%")
                    ->orWhereHas('position', fn ($p) => $p->where('title', 'ILIKE', "%{$filters['search']}%"));
            }))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function show(JobRequisition $requisition): JobRequisition
    {
        return $requisition->load([
            'department', 'position', 'salaryGrade', 'requester', 'approver',
            'postings', 'hirings', 'approvals.user', 'approvalLogs',
        ]);
    }

    public function create(array $data, User $actor): JobRequisition
    {
        return DB::transaction(function () use ($data, $actor): JobRequisition {
            $requisition = JobRequisition::create([
                ...$data,
                'requested_by' => $actor->id,
                'status' => RequisitionStatus::Draft->value,
            ]);

            $requisition->logApproval('created', 'created', $actor, 'Requisition created');

            return $requisition;
        });
    }

    public function update(JobRequisition $requisition, array $data, User $actor): JobRequisition
    {
        if (! in_array($requisition->status, [RequisitionStatus::Draft, RequisitionStatus::Rejected])) {
            throw new DomainException(
                'Can only edit requisitions in draft or rejected status.',
                'REQUISITION_NOT_EDITABLE',
                422,
                ['current_status' => $requisition->status->value],
            );
        }

        return DB::transaction(function () use ($requisition, $data): JobRequisition {
            $requisition->update($data);

            return $requisition->fresh();
        });
    }

    public function submit(JobRequisition $requisition, User $actor): JobRequisition
    {
        $result = DB::transaction(function () use ($requisition, $actor): JobRequisition {
            $this->stateMachine->transition($requisition, RequisitionStatus::PendingApproval);
            $requisition->save();

            $requisition->logApproval('submitted', 'submitted', $actor, 'Submitted for approval');

            return $requisition;
        });

        // Notify HR managers (outside transaction so notification failure doesn't roll back)
        $hrManagers = User::whereHas('roles', fn ($q) => $q->where('name', 'manager'))
            ->whereHas('departments', fn ($q) => $q->where('code', 'HR'))
            ->get();
        foreach ($hrManagers as $mgr) {
            $mgr->notify(RequisitionSubmittedNotification::fromModel($result->load(['requester', 'position', 'department'])));
        }

        return $result;
    }

    public function approve(JobRequisition $requisition, User $actor, ?string $remarks = null): JobRequisition
    {
        // SoD: approver cannot be the requester
        if ($actor->id === $requisition->requested_by) {
            throw new DomainException(
                'You cannot approve your own requisition.',
                'SOD_SELF_APPROVAL',
                403,
                ['user_id' => $actor->id, 'requested_by' => $requisition->requested_by],
            );
        }

        $result = DB::transaction(function () use ($requisition, $actor, $remarks): JobRequisition {
            $this->stateMachine->transition($requisition, RequisitionStatus::Approved);
            $requisition->approved_by = $actor->id;
            $requisition->approved_at = now();
            $requisition->save();

            $requisition->approvals()->create([
                'user_id' => $actor->id,
                'action' => 'approved',
                'remarks' => $remarks,
                'acted_at' => now(),
            ]);

            $requisition->logApproval('approval', 'approved', $actor, $remarks);

            return $requisition;
        });

        // Notify requester (outside transaction)
        $result->requester?->notify(
            RequisitionDecidedNotification::fromModel($result, 'approved', $actor->name, $remarks)
        );

        return $result;
    }

    public function reject(JobRequisition $requisition, User $actor, string $reason): JobRequisition
    {
        $result = DB::transaction(function () use ($requisition, $actor, $reason): JobRequisition {
            $this->stateMachine->transition($requisition, RequisitionStatus::Rejected);
            $requisition->rejected_at = now();
            $requisition->rejection_reason = $reason;
            $requisition->save();

            $requisition->approvals()->create([
                'user_id' => $actor->id,
                'action' => 'rejected',
                'remarks' => $reason,
                'acted_at' => now(),
            ]);

            $requisition->logApproval('approval', 'rejected', $actor, $reason);

            return $requisition;
        });

        // Notify requester (outside transaction)
        $result->requester?->notify(
            RequisitionDecidedNotification::fromModel($result, 'rejected', $actor->name, $reason)
        );

        return $result;
    }

    public function cancel(JobRequisition $requisition, User $actor, string $reason): JobRequisition
    {
        return DB::transaction(function () use ($requisition, $actor, $reason): JobRequisition {
            $this->stateMachine->transition($requisition, RequisitionStatus::Cancelled);
            $requisition->save();

            $requisition->logApproval('cancelled', 'cancelled', $actor, $reason);

            return $requisition;
        });
    }

    public function hold(JobRequisition $requisition, User $actor, string $reason): JobRequisition
    {
        return DB::transaction(function () use ($requisition, $actor, $reason): JobRequisition {
            $this->stateMachine->transition($requisition, RequisitionStatus::OnHold);
            $requisition->save();

            $requisition->logApproval('on_hold', 'on_hold', $actor, $reason);

            return $requisition;
        });
    }

    public function resume(JobRequisition $requisition, User $actor): JobRequisition
    {
        return DB::transaction(function () use ($requisition, $actor): JobRequisition {
            $this->stateMachine->transition($requisition, RequisitionStatus::Open);
            $requisition->save();

            $requisition->logApproval('resumed', 'resumed', $actor, 'Requisition resumed from on-hold');

            return $requisition;
        });
    }

    public function open(JobRequisition $requisition, User $actor): JobRequisition
    {
        return DB::transaction(function () use ($requisition, $actor): JobRequisition {
            $this->stateMachine->transition($requisition, RequisitionStatus::Open);
            $requisition->save();

            $requisition->logApproval('opened', 'opened', $actor, 'Requisition opened for applications');

            return $requisition;
        });
    }
}
