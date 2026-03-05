<?php

declare(strict_types=1);

namespace App\Domains\Leave\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\Leave\Models\LeaveBalance;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Leave\Models\LeaveType;
use App\Events\Leave\LeaveRequestDecided;
use App\Events\Leave\LeaveRequestFiled;
use App\Models\User;
use App\Notifications\LeaveDecidedNotification;
use App\Notifications\LeaveFiledNotification;
use App\Notifications\LeaveSupervisorEndorsedNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\InsufficientLeaveBalanceException;
use App\Shared\Exceptions\SodViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Leave request lifecycle service.
 *
 * Business rules:
 *  LV-001: Cannot approve a request if balance would go negative
 *  LV-004: Approver must differ from submitter (Segregation of Duties)
 *  LV-005: Rejected/cancelled requests do not deduct balance
 *  LV-006: LWOP always approved (no balance check), but deducts absent flag for payroll
 *  LV-008: Half-day requests count as 0.5 days
 */
final class LeaveRequestService implements ServiceContract
{
    /**
     * Submit a leave request for review.
     *
     * Workflow routing:
     * - Staff → Supervisor endorsement → Manager approval
     * - Supervisor → Manager approval
     * - Manager → Executive approval
     *
     * @param  array<string, mixed>  $data
     * @param  string  $requesterRole  staff|head|officer|manager
     *
     * @throws DomainException|InsufficientLeaveBalanceException
     */
    public function submit(
        Employee $employee,
        array $data,
        int $submittedByUserId,
        string $requesterRole = 'staff'
    ): LeaveRequest {
        $request = DB::transaction(function () use ($employee, $data, $submittedByUserId, $requesterRole): LeaveRequest {
            $leaveType = LeaveType::findOrFail($data['leave_type_id']);
            $year = date('Y', strtotime($data['date_from']));

            $totalDays = isset($data['is_half_day']) && $data['is_half_day'] ? 0.5 : $data['total_days'];

            // LV-006: LWOP skips balance check
            if ($leaveType->code !== 'LWOP') {
                $balance = LeaveBalance::firstOrCreate(
                    ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => $year],
                    ['opening_balance' => 0, 'accrued' => 0, 'adjusted' => 0, 'used' => 0, 'monetized' => 0],
                );

                if (! $balance->hasSufficientBalance($totalDays)) {
                    throw new InsufficientLeaveBalanceException(
                        $leaveType->name,
                        $totalDays,          // requested
                        $balance->balance,   // available
                    );
                }
            }

            // Determine initial status based on requester role
            // Manager-level requests go to 'pending_executive' status
            $isManagerRole = in_array($requesterRole, ['manager'], true);
            $initialStatus = $isManagerRole ? 'pending_executive' : 'submitted';

            return LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'submitted_by' => $submittedByUserId,
                'requester_role' => $requesterRole,
                'date_from' => $data['date_from'],
                'date_to' => $data['date_to'],
                'total_days' => $totalDays,
                'is_half_day' => $data['is_half_day'] ?? false,
                'half_day_period' => $data['half_day_period'] ?? null,
                'reason' => $data['reason'],
                'status' => $initialStatus,
            ]);
        });

        // Notify based on workflow
        if (in_array($requesterRole, ['manager'], true)) {
            $this->notifyExecutiveOfManagerRequest($request, $employee);
        } else {
            $this->notifyManagerOfFiledRequest($request, $employee);
        }

        return $request;
    }

    /**
     * Supervisor first-level approval.
     * Moves request from 'submitted' to 'supervisor_approved'.
     *
     * @throws SodViolationException
     */
    public function supervisorApprove(LeaveRequest $request, int $supervisorUserId, ?string $remarks = null): LeaveRequest
    {
        // LV-004: SoD check
        if ($request->submitted_by === $supervisorUserId) {
            throw new SodViolationException('leave_request', 'supervisor_approve', 'Supervisor must differ from submitter (LV-004).');
        }

        if ($request->status !== 'submitted') {
            throw new DomainException('Only submitted requests can be supervisor-approved.', 'LV_NOT_SUBMITTED', 422);
        }

        $request->status = 'supervisor_approved';
        $request->supervisor_id = $supervisorUserId;
        $request->supervisor_remarks = $remarks;
        $request->supervisor_reviewed_at = now();
        $request->save();

        // Notify manager that supervisor has approved
        $this->notifyManagerOfSupervisorApproval($request);

        return $request;
    }

    /**
     * Manager final approval - deducts from balance.
     *
     * Workflow:
     * - Staff requests: must be supervisor_approved first
     * - Supervisor requests: can be approved directly (no supervisor endorsement needed)
     *
     * @throws SodViolationException|InsufficientLeaveBalanceException
     */
    public function approve(LeaveRequest $request, int $reviewedByUserId, ?string $remarks = null): LeaveRequest
    {
        // LV-004: SoD check
        if ($request->submitted_by === $reviewedByUserId) {
            throw new SodViolationException('leave_request', 'approve', 'Leave request approver must differ from submitter (LV-004).');
        }

        // Manager cannot approve their own requests through this method
        // Manager requests must go through executive approval
        if (in_array($request->requester_role, ['manager'], true)) {
            throw new DomainException(
                'Manager requests must be approved by executive. Use executiveApprove method.',
                'LV_USE_EXECUTIVE_APPROVAL',
                422
            );
        }

        // Supervisor requests can be approved directly
        // Staff requests must be supervisor_approved first
        if ($request->requester_role === 'staff' && ! in_array($request->status, ['supervisor_approved'], true)) {
            throw new DomainException('Staff requests must be supervisor-endorsed first.', 'LV_NOT_SUPERVISOR_APPROVED', 422);
        }

        // For backward compatibility: allow 'submitted' status for supervisor requests
        if (! in_array($request->status, ['supervisor_approved', 'submitted'], true)) {
            throw new DomainException('Request must be supervisor-approved first.', 'LV_NOT_SUPERVISOR_APPROVED', 422);
        }

        $request = DB::transaction(function () use ($request, $reviewedByUserId, $remarks): LeaveRequest {
            $leaveType = $request->leaveType;
            $year = $request->date_from->year;

            // LV-006: LWOP does not deduct balance
            if ($leaveType->code !== 'LWOP') {
                $balance = LeaveBalance::where([
                    'employee_id' => $request->employee_id,
                    'leave_type_id' => $request->leave_type_id,
                    'year' => $year,
                ])->lockForUpdate()->firstOrFail();

                if (! $balance->hasSufficientBalance($request->total_days)) {
                    throw new InsufficientLeaveBalanceException(
                        $leaveType->name,
                        $request->total_days, // requested
                        $balance->balance,    // available
                    );
                }

                $balance->used += $request->total_days;
                $balance->save();
            }

            $request->status = 'approved';
            $request->reviewed_by = $reviewedByUserId;
            $request->review_remarks = $remarks;
            $request->reviewed_at = now();
            $request->save();

            return $request;
        });

        $this->notifyEmployeeOfDecision($request, 'approved', $remarks);

        return $request;
    }

    /**
     * Executive approval for manager-filed requests.
     * Only executives can approve manager requests.
     *
     * @throws SodViolationException|InsufficientLeaveBalanceException
     */
    public function executiveApprove(
        LeaveRequest $request,
        int $executiveUserId,
        ?string $remarks = null
    ): LeaveRequest {
        // Only manager requests can be executive-approved
        if (! in_array($request->requester_role, ['manager'], true)) {
            throw new DomainException(
                'Executive approval is only for manager-filed requests.',
                'LV_NOT_MANAGER_REQUEST',
                422
            );
        }

        if ($request->status !== 'pending_executive') {
            throw new DomainException(
                'Request must be pending executive approval.',
                'LV_NOT_PENDING_EXECUTIVE',
                422
            );
        }

        // LV-004: SoD check - executive must differ from submitter
        if ($request->submitted_by === $executiveUserId) {
            throw new SodViolationException(
                'leave_request',
                'executive_approve',
                'Executive must differ from submitter (LV-004).'
            );
        }

        $request = DB::transaction(function () use ($request, $executiveUserId, $remarks): LeaveRequest {
            $leaveType = $request->leaveType;
            $year = $request->date_from->year;

            // LV-006: LWOP does not deduct balance
            if ($leaveType->code !== 'LWOP') {
                $balance = LeaveBalance::where([
                    'employee_id' => $request->employee_id,
                    'leave_type_id' => $request->leave_type_id,
                    'year' => $year,
                ])->lockForUpdate()->firstOrFail();

                if (! $balance->hasSufficientBalance($request->total_days)) {
                    throw new InsufficientLeaveBalanceException(
                        $leaveType->name,
                        $request->total_days,
                        $balance->balance,
                    );
                }

                $balance->used += $request->total_days;
                $balance->save();
            }

            $request->status = 'approved';
            $request->executive_id = $executiveUserId;
            $request->executive_remarks = $remarks;
            $request->executive_reviewed_at = now();
            $request->reviewed_by = $executiveUserId; // Also set as final reviewer
            $request->review_remarks = $remarks;
            $request->reviewed_at = now();
            $request->save();

            return $request;
        });

        $this->notifyEmployeeOfDecision($request, 'approved', $remarks);

        return $request;
    }

    /**
     * Executive rejection for manager-filed requests.
     *
     * @throws SodViolationException
     */
    public function executiveReject(
        LeaveRequest $request,
        int $executiveUserId,
        string $remarks
    ): LeaveRequest {
        if (! in_array($request->requester_role, ['manager'], true)) {
            throw new DomainException(
                'Executive rejection is only for manager-filed requests.',
                'LV_NOT_MANAGER_REQUEST',
                422
            );
        }

        if ($request->status !== 'pending_executive') {
            throw new DomainException(
                'Request must be pending executive approval.',
                'LV_NOT_PENDING_EXECUTIVE',
                422
            );
        }

        // LV-004: SoD
        if ($request->submitted_by === $executiveUserId) {
            throw new SodViolationException(
                'leave_request',
                'executive_reject',
                'Executive must differ from submitter (LV-004).'
            );
        }

        $request->status = 'rejected';
        $request->executive_id = $executiveUserId;
        $request->executive_remarks = $remarks;
        $request->executive_reviewed_at = now();
        $request->reviewed_by = $executiveUserId;
        $request->review_remarks = $remarks;
        $request->reviewed_at = now();
        $request->save();

        $this->notifyEmployeeOfDecision($request, 'rejected', $remarks);

        return $request;
    }

    /**
     * Reject the leave request — balance is NOT deducted.
     * Can be rejected by supervisor or manager.
     *
     * @throws SodViolationException
     */
    public function reject(LeaveRequest $request, int $reviewedByUserId, string $remarks): LeaveRequest
    {
        // LV-004: SoD
        if ($request->submitted_by === $reviewedByUserId) {
            throw new SodViolationException('leave_request', 'reject', 'Leave request rejector must differ from submitter (LV-004).');
        }

        if (! $request->isPending()) {
            throw new DomainException('Only pending requests can be rejected.', 'LV_NOT_PENDING', 422);
        }

        // If supervisor rejects, record it as supervisor rejection
        if ($request->status === 'submitted') {
            $request->supervisor_id = $reviewedByUserId;
            $request->supervisor_remarks = $remarks;
            $request->supervisor_reviewed_at = now();
        }

        $request->status = 'rejected';
        $request->reviewed_by = $reviewedByUserId;
        $request->review_remarks = $remarks;
        $request->reviewed_at = now();
        $request->save();

        $this->notifyEmployeeOfDecision($request, 'rejected', $remarks);

        return $request;
    }

    /**
     * Cancel a leave request.
     * If it was approved, the balance is restored.
     *
     * @throws DomainException
     */
    public function cancel(LeaveRequest $request): LeaveRequest
    {
        if (! $request->isCancellable()) {
            throw new DomainException(
                'Only draft or submitted requests can be cancelled.',
                'LV_NOT_CANCELLABLE',
                422
            );
        }

        $request = DB::transaction(function () use ($request): LeaveRequest {
            $request->status = 'cancelled';
            $request->save();

            return $request;
        });

        return $request;
    }

    // ── Private notification helpers ──────────────────────────────────────────

    /**
     * Notify the employee's direct supervisor that a leave request has been filed.
     *
     * Workflow: Staff → Supervisor (endorse) → Department Manager (approve)
     * The supervisor is resolved via employee.reports_to.
     * If no supervisor is configured, fall back to the department manager.
     */
    private function notifyManagerOfFiledRequest(LeaveRequest $request, Employee $employee): void
    {
        try {
            $request->loadMissing('employee', 'leaveType');

            // Primary: direct supervisor via reports_to
            if ($employee->reports_to) {
                $supervisorUserId = Employee::find($employee->reports_to)?->getAttribute('user_id');
                if ($supervisorUserId) {
                    $supervisorUser = User::find($supervisorUserId);
                    if ($supervisorUser) {
                        $supervisorUser->notify(new LeaveFiledNotification($request));
                        LeaveRequestFiled::dispatch($request, $supervisorUser->id);

                        return; // Supervisor found — do not skip ahead to manager
                    }
                }
            }

            // Fallback: notify the department manager when no supervisor is configured
            if ($employee->department_id) {
                $deptManager = User::role(['manager'])
                    ->whereHas('departments', fn ($q) => $q->where('departments.id', $employee->department_id))
                    ->whereHas('permissions', fn ($q) => $q->where('name', 'leaves.approve'))
                    ->first();

                if ($deptManager) {
                    $deptManager->notify(new LeaveFiledNotification($request));
                    LeaveRequestFiled::dispatch($request, $deptManager->id);
                }
            }
        } catch (\Throwable) {
            // Non-fatal — notification failure must not block the leave submission
        }
    }

    /**
     * Notify the Department Manager when the supervisor has endorsed the request.
     *
     * Workflow step 2: Supervisor endorsed → Department Manager notified for final approval.
     * The department manager is scoped to the employee's own department (not HR).
     */
    private function notifyManagerOfSupervisorApproval(LeaveRequest $request): void
    {
        try {
            $employee = $request->employee;
            if (! $employee || ! $employee->department_id) {
                return;
            }

            // Resolve the department manager of the employee's own department
            $deptManager = User::role(['manager'])
                ->whereHas('departments', fn ($q) => $q->where('departments.id', $employee->department_id))
                ->whereHas('permissions', fn ($q) => $q->where('name', 'leaves.approve'))
                ->first();

            if (! $deptManager) {
                return;
            }

            $supervisorName = 'Supervisor';
            if ($request->supervisor_id) {
                $supervisorUser = User::find($request->supervisor_id);
                $supervisorName = $supervisorUser ? $supervisorUser->name : 'Supervisor';
            }

            $deptManager->notify(new LeaveSupervisorEndorsedNotification(
                $request->loadMissing('employee', 'leaveType'),
                $supervisorName,
                $request->supervisor_remarks ?? null,
            ));
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Notify executives when a manager files a leave request.
     */
    private function notifyExecutiveOfManagerRequest(LeaveRequest $request, Employee $employee): void
    {
        try {
            // Find executives (users with executive role)
            $executives = User::role('executive')->get();

            if ($executives->isEmpty()) {
                // Fallback: notify admins if no executives
                $executives = User::role('admin')->get();
            }

            foreach ($executives as $executive) {
                $executive->notify(new LeaveFiledNotification($request->loadMissing('employee', 'leaveType')));
            }
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Notify the leave-request's linked employee user of an approval/rejection decision.
     */
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

            $employeeUser->notify(
                new LeaveDecidedNotification($request->loadMissing('leaveType'), $decision, $remarks)
            );
            LeaveRequestDecided::dispatch($request, $employeeUserId, $decision, $remarks);
        } catch (\Throwable) {
            // Non-fatal
        }
    }
}
