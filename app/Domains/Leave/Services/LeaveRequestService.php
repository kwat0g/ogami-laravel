<?php

declare(strict_types=1);

namespace App\Domains\Leave\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Leave\Models\LeaveType;
use App\Domains\Leave\StateMachines\LeaveRequestStateMachine;
use App\Events\Leave\LeaveRequestDecided;
use App\Events\Leave\LeaveRequestFiled;
use App\Models\User;
use App\Notifications\LeaveDecidedNotification;
use App\Notifications\LeaveFiledNotification;
use App\Notifications\LeaveSupervisorEndorsedNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\SodViolationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Leave request lifecycle service — simplified requester-specific approval flow.
 *
 * Workflow:
 *   staff        → submitted → head_approved    → approved
 *   head_officer → submitted → manager_approved → approved
 *   dept_manager → submitted → hr_approved      → approved
 *   hr_manager   → submitted                     → approved
 */
final class LeaveRequestService implements ServiceContract
{
    public function __construct(
        private readonly LeaveRequestStateMachine $stateMachine,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    public function submit(Employee $employee, array $data, int $submittedByUserId): LeaveRequest
    {
        $request = DB::transaction(function () use ($employee, $data, $submittedByUserId): LeaveRequest {
            $leaveType = LeaveType::findOrFail($data['leave_type_id']);
            $isHalfDay = isset($data['is_half_day']) && $data['is_half_day'];

            if ($isHalfDay) {
                $totalDays = 0.5;
            } elseif (isset($data['total_days']) && $data['total_days'] > 0) {
                $totalDays = (float) $data['total_days'];
            } else {
                $from = new Carbon($data['date_from']);
                $to = new Carbon($data['date_to']);
                $totalDays = 0.0;
                $current = $from->copy();

                while ($current->lte($to)) {
                    if (! $current->isWeekend()) {
                        $totalDays += 1.0;
                    }
                    $current->addDay();
                }

                if ($totalDays === 0.0 && $from->equalTo($to) && ! $from->isWeekend()) {
                    $totalDays = 1.0;
                }
            }

            return LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'submitted_by' => $submittedByUserId,
                'requester_type' => $this->resolveRequesterType($employee),
                'date_from' => $data['date_from'],
                'date_to' => $data['date_to'],
                'total_days' => $totalDays,
                'is_half_day' => $isHalfDay,
                'half_day_period' => $data['half_day_period'] ?? null,
                'reason' => $data['reason'],
                'status' => 'submitted',
            ]);
        });

        $this->notifyNextApprover($request->loadMissing('employee', 'leaveType'));

        return $request;
    }

    /**
     * @throws SodViolationException|DomainException
     */
    public function headApprove(LeaveRequest $request, int $headUserId, ?string $remarks = null): LeaveRequest
    {
        $this->guardApprover($request, $headUserId, 'head_approve', ['staff'], ['submitted']);

        $this->stateMachine->transition($request, 'head_approved');
        $request->head_id = $headUserId;
        $request->head_remarks = $remarks;
        $request->head_approved_at = now();
        $request->save();

        $this->notifyNextApprover($request->loadMissing('employee', 'leaveType'));

        return $request;
    }

    /**
     * @throws SodViolationException|DomainException
     */
    public function managerApprove(LeaveRequest $request, int $managerUserId, ?string $remarks = null): LeaveRequest
    {
        $this->guardApprover($request, $managerUserId, 'manager_approve', ['head_officer'], ['submitted']);

        $this->stateMachine->transition($request, 'manager_approved');
        $request->manager_approved_by = $managerUserId;
        $request->manager_approved_remarks = $remarks;
        $request->manager_approved_at = now();
        $request->save();

        $this->notifyNextApprover($request->loadMissing('employee', 'leaveType'));

        return $request;
    }

    /**
     * @throws SodViolationException|DomainException
     */
    public function hrApprove(LeaveRequest $request, int $hrUserId, ?string $remarks = null): LeaveRequest
    {
        $this->guardApprover($request, $hrUserId, 'hr_approve', ['staff', 'head_officer', 'dept_manager'], [
            'head_approved',
            'manager_approved',
            'submitted',
        ]);

        if ($request->requester_type === 'dept_manager') {
            if ($request->status !== 'submitted') {
                throw new DomainException('Department manager requests must be submitted before HR approval.', 'LV_NOT_SUBMITTED', 422);
            }

            $this->stateMachine->transition($request, 'hr_approved');
        } else {
            if (! in_array($request->status, ['head_approved', 'manager_approved'], true)) {
                throw new DomainException('Only head-approved or manager-approved requests can be finalized by HR.', 'LV_NOT_READY_FOR_HR', 422);
            }

            $this->stateMachine->transition($request, 'approved');
            $this->deductBalanceIfAvailable($request);
        }

        $request->hr_approved_by = $hrUserId;
        $request->hr_remarks = $remarks;
        $request->hr_approved_at = now();
        $request->save();

        if ($request->status === 'approved') {
            $this->notifyEmployeeOfDecision($request->loadMissing('employee', 'leaveType'), 'approved', $remarks);
        } else {
            $this->notifyNextApprover($request->loadMissing('employee', 'leaveType'));
        }

        return $request;
    }

    /**
     * @throws SodViolationException|DomainException
     */
    public function vpApprove(LeaveRequest $request, int $vpUserId, ?string $remarks = null): LeaveRequest
    {
        $this->guardApprover($request, $vpUserId, 'vp_approve', ['dept_manager', 'hr_manager'], [
            'submitted',
            'hr_approved',
        ]);

        if (
            ($request->requester_type === 'dept_manager' && $request->status !== 'hr_approved')
            || ($request->requester_type === 'hr_manager' && $request->status !== 'submitted')
        ) {
            throw new DomainException('Leave request is not at the VP approval step.', 'LV_NOT_READY_FOR_VP', 422);
        }

        DB::transaction(function () use ($request, $vpUserId, $remarks): void {
            $this->stateMachine->transition($request, 'approved');
            $this->deductBalanceIfAvailable($request);
            $request->vp_id = $vpUserId;
            $request->vp_remarks = $remarks;
            $request->vp_noted_at = now();
            $request->save();
        });

        $this->notifyEmployeeOfDecision($request->loadMissing('employee', 'leaveType'), 'approved', $remarks);

        return $request;
    }

    /**
     * @throws SodViolationException|DomainException
     */
    public function reject(LeaveRequest $request, int $rejectedByUserId, string $remarks): LeaveRequest
    {
        if ($request->submitted_by === $rejectedByUserId) {
            throw new SodViolationException('leave_request', 'reject', 'Approver must differ from request submitter.');
        }

        if (! $request->isPending()) {
            throw new DomainException('Only pending requests can be rejected.', 'LV_NOT_PENDING', 422);
        }

        match ($request->status) {
            'submitted' => match ($request->requester_type) {
                'staff' => $request->fill([
                    'head_id' => $rejectedByUserId,
                    'head_remarks' => $remarks,
                    'head_approved_at' => now(),
                ]),
                'head_officer' => $request->fill([
                    'manager_approved_by' => $rejectedByUserId,
                    'manager_approved_remarks' => $remarks,
                    'manager_approved_at' => now(),
                ]),
                'dept_manager' => $request->fill([
                    'hr_approved_by' => $rejectedByUserId,
                    'hr_remarks' => $remarks,
                    'hr_approved_at' => now(),
                ]),
                'hr_manager' => $request->fill([
                    'vp_id' => $rejectedByUserId,
                    'vp_remarks' => $remarks,
                    'vp_noted_at' => now(),
                ]),
                default => null,
            },
            'head_approved' => $request->fill([
                'hr_approved_by' => $rejectedByUserId,
                'hr_remarks' => $remarks,
                'hr_approved_at' => now(),
            ]),
            'manager_approved' => $request->fill([
                'hr_approved_by' => $rejectedByUserId,
                'hr_remarks' => $remarks,
                'hr_approved_at' => now(),
            ]),
            'hr_approved' => $request->fill([
                'vp_id' => $rejectedByUserId,
                'vp_remarks' => $remarks,
                'vp_noted_at' => now(),
            ]),
            default => null,
        };

        $this->stateMachine->transition($request, 'rejected');
        $request->save();

        $this->notifyEmployeeOfDecision($request->loadMissing('employee', 'leaveType'), 'rejected', $remarks);

        return $request;
    }

    /**
     * @throws DomainException
     */
    public function cancel(LeaveRequest $request): LeaveRequest
    {
        if (! $request->isCancellable()) {
            throw new DomainException('Only draft or submitted requests can be cancelled.', 'LV_NOT_CANCELLABLE', 422);
        }

        DB::transaction(function () use ($request): void {
            $this->stateMachine->transition($request, 'cancelled');
            $request->save();
        });

        return $request;
    }

    private function resolveRequesterType(Employee $employee): string
    {
        $requester = $employee->user;
        $departmentCode = (string) ($employee->department?->code ?? '');

        if ($requester?->hasRole('manager')) {
            return $departmentCode === 'HR' ? 'hr_manager' : 'dept_manager';
        }

        if ($requester?->hasAnyRole(['head', 'officer'])) {
            return 'head_officer';
        }

        return 'staff';
    }

    /**
     * @param  list<string>  $requesterTypes
     * @param  list<string>  $statuses
     *
     * @throws SodViolationException|DomainException
     */
    private function guardApprover(LeaveRequest $request, int $actorUserId, string $action, array $requesterTypes, array $statuses): void
    {
        if ($request->submitted_by === $actorUserId) {
            throw new SodViolationException('leave_request', $action, 'Approver must differ from request submitter.');
        }

        $employeeUserId = $request->employee?->user_id;
        if ($employeeUserId !== null && $employeeUserId === $actorUserId) {
            throw new SodViolationException('leave_request', $action, 'Approver must differ from leave owner.');
        }

        if (! in_array($request->requester_type, $requesterTypes, true)) {
            throw new DomainException('Leave request is not in the expected requester chain.', 'LV_INVALID_REQUESTER_TYPE', 422);
        }

        if (! in_array($request->status, $statuses, true)) {
            throw new DomainException('Leave request is not at the expected approval step.', 'LV_INVALID_STATUS', 422);
        }
    }

    private function deductBalanceIfAvailable(LeaveRequest $request): void
    {
        $leaveType = $request->leaveType()->first();
        if ($leaveType === null || ! $leaveType->is_paid) {
            return;
        }

        $balance = LeaveBalance::where([
            'employee_id' => $request->employee_id,
            'leave_type_id' => $request->leave_type_id,
            'year' => $request->date_from->year,
        ])->lockForUpdate()->first();

        if ($balance === null || $balance->balance < $request->total_days) {
            return;
        }

        $balance->used += $request->total_days;
        $balance->save();
    }

    private function notifyNextApprover(LeaveRequest $request): void
    {
        try {
            $request->loadMissing('employee', 'leaveType');

            $users = match ([$request->requester_type, $request->status]) {
                ['staff', 'submitted'] => User::permission('leaves.head_approve')
                    ->whereHas('departments', fn ($q) => $q->where('departments.id', $request->employee?->department_id))
                    ->get(),
                ['head_officer', 'submitted'] => User::permission('leaves.manager_approve')
                    ->whereHas('departments', fn ($q) => $q->where('departments.id', $request->employee?->department_id))
                    ->get(),
                ['dept_manager', 'submitted'], ['staff', 'head_approved'], ['head_officer', 'manager_approved'] => User::permission('leaves.hr_approve')
                    ->whereHas('departments', fn ($q) => $q->where('departments.code', 'HR'))
                    ->get(),
                ['dept_manager', 'hr_approved'], ['hr_manager', 'submitted'] => User::permission('leaves.vp_approve')->get(),
                default => collect(),
            };

            foreach ($users as $user) {
                $user->notify(LeaveFiledNotification::fromModel($request));
            }

            if ($users->isNotEmpty()) {
                LeaveRequestFiled::dispatch($request, $users->first()->id);
            }
        } catch (\Throwable) {
            // Non-fatal notification failure
        }
    }

    private function notifyEmployeeOfDecision(LeaveRequest $request, string $decision, ?string $remarks): void
    {
        try {
            $employeeUserId = $request->employee?->getAttribute('user_id');
            if (! $employeeUserId) {
                return;
            }

            $employeeUser = User::find($employeeUserId);
            if (! $employeeUser) {
                return;
            }

            $employeeUser->notify(LeaveDecidedNotification::fromModel($request->loadMissing('leaveType'), $decision, $remarks));
            LeaveRequestDecided::dispatch($request, $employeeUserId, $decision, $remarks);
        } catch (\Throwable) {
            // Non-fatal notification failure
        }
    }
};
