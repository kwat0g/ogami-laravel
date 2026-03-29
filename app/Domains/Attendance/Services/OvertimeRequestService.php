<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Models\OvertimeRequest;
use App\Domains\Attendance\StateMachines\OvertimeRequestStateMachine;
use App\Domains\HR\Models\Employee;
use App\Models\User;
use App\Notifications\OvertimeCancelledNotification;
use App\Notifications\OvertimeDecidedNotification;
use App\Notifications\OvertimeManagerRequestedNotification;
use App\Notifications\OvertimeRequestedNotification;
use App\Notifications\OvertimeSupervisorEndorsedNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\SodViolationException;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Overtime pre-approval request service.
 *
 * Business rules:
 *  ATT-005: OT approval must be timestamped before the pay period cutoff.
 *  SoD:     The approver's user_id must differ from the employee's user_id.
 *  ATT-008: Employees on approved leave cannot have simultaneous OT requests.
 *
 * Approval workflow:
 *  Staff role     : pending → supervisor_approved → approved
 *  Supervisor role: pending → approved  (manager approves directly)
 *  Manager role   : pending_executive   → approved  (executive approves)
 */
final class OvertimeRequestService implements ServiceContract
{
    public function __construct(
        private readonly OvertimeRequestStateMachine $stateMachine,
    ) {}
    /**
     * List overtime requests with optional filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = OvertimeRequest::with('employee')
            ->latest();

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['work_date_from'])) {
            $query->where('work_date', '>=', $filters['work_date_from']);
        }

        if (isset($filters['work_date_to'])) {
            $query->where('work_date', '<=', $filters['work_date_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Submit a new overtime request for pre-approval.
     *
     * Routing:
     *  - Staff      → supervisor notified (pending)
     *  - Supervisor → manager notified directly (pending)
     *  - Manager    → executive notified (pending_executive)
     *
     * @param  array<string, mixed>  $data  { work_date, ot_start_time, ot_end_time, reason }
     *
     * @throws DomainException
     */
    public function submit(Employee $employee, array $data, int $submittedByUserId): OvertimeRequest
    {
        // Determine requester role from the submitting user
        $submitter = User::find($submittedByUserId);
        $requesterRole = $this->resolveRequesterRole($submitter);
        $isManagerRole = $requesterRole === 'manager';

        try {
            $overtimeRequest = DB::transaction(function () use ($employee, $data, $submittedByUserId, $requesterRole, $isManagerRole): OvertimeRequest {
                $workDate = Carbon::parse($data['work_date']);
                $otStart = Carbon::parse($data['work_date'].' '.$data['ot_start_time']);
                $otEnd = Carbon::parse($data['work_date'].' '.$data['ot_end_time']);
                $requestedMinutes = (int) ceil($otStart->diffInSeconds($otEnd) / 60);

                // Validate requested minutes (min 30 minutes, max 4 hours = 240 minutes per day)
                if ($requestedMinutes < 30 || $requestedMinutes > 240) {
                    throw new DomainException(
                        'Requested overtime must be between 30 and 240 minutes (4 hours).',
                        'ATT_OT_MINUTES_OUT_OF_RANGE',
                        422,
                    );
                }

                // Cannot request OT for a date in the past beyond 1 day
                if ($workDate->lt(Carbon::today()->subDay())) {
                    throw new DomainException(
                        'Overtime requests cannot be filed for dates more than 1 day in the past.',
                        'ATT_OT_BACKDATING_NOT_ALLOWED',
                        422,
                    );
                }

                // Check for duplicate request on the same date
                $existing = OvertimeRequest::where('employee_id', $employee->id)
                    ->where('work_date', $workDate->toDateString())
                    ->whereIn('status', ['pending', 'supervisor_approved', 'pending_executive', 'approved'])
                    ->exists();

                if ($existing) {
                    throw new DomainException(
                        'An overtime request already exists for this employee on the specified date.',
                        'ATT_OT_DUPLICATE',
                        422,
                    );
                }

                $initialStatus = $isManagerRole ? 'pending_executive' : 'pending';

                return OvertimeRequest::create([
                    'employee_id' => $employee->id,
                    'requester_role' => $requesterRole,
                    'requested_by' => $submittedByUserId,
                    'work_date' => $workDate->toDateString(),
                    'ot_start_time' => $data['ot_start_time'],
                    'ot_end_time' => $data['ot_end_time'],
                    'requested_minutes' => $requestedMinutes,
                    'reason' => $data['reason'],
                    'status' => $initialStatus,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(
                'An overtime request already exists for this employee on the specified date.',
                'ATT_OT_DUPLICATE',
                422,
            );
        }

        // Notify based on workflow path
        if ($isManagerRole) {
            $this->notifyExecutiveOfManagerRequest($overtimeRequest, $employee);
        } elseif ($requesterRole === 'head') {
            // Head requests: notify manager directly — no head endorsement step
            $this->notifyManagerOfOvertimeRequest($overtimeRequest, $employee, skipSupervisor: true);
        } else {
            // Staff requests: notify head/supervisor first
            $this->notifyManagerOfOvertimeRequest($overtimeRequest, $employee);
        }

        return $overtimeRequest;
    }

    /**
     * Supervisor first-level endorsement.
     * Moves a staff request from 'pending' → 'supervisor_approved'.
     * Then notifies the Department Manager for final approval.
     *
     * @throws SodViolationException|DomainException
     */
    public function supervisorEndorse(
        OvertimeRequest $request,
        int $supervisorUserId,
        ?string $remarks = null,
    ): OvertimeRequest {
        // SoD: Supervisor must not be the employee who owns this OT request
        if ($request->employee?->user_id === $supervisorUserId) {
            throw new SodViolationException(
                'overtime_request',
                'supervisor_endorse',
                'Supervisor must differ from the requesting employee (ATT-003).',
            );
        }

        if ($request->status !== 'pending') {
            throw new DomainException(
                'Only pending overtime requests can be endorsed by the supervisor.',
                'ATT_OT_NOT_PENDING',
                422,
            );
        }

        // Supervisor endorsement is only for staff (or null/legacy) requests
        if ($request->requester_role === 'manager') {
            throw new DomainException(
                'Manager overtime requests must be approved by the Executive.',
                'ATT_OT_USE_EXECUTIVE_APPROVAL',
                422,
            );
        }

        if ($request->requester_role === 'head') {
            throw new DomainException(
                'Head overtime requests are approved directly by the manager — no endorsement step required.',
                'ATT_OT_HEAD_NO_ENDORSEMENT',
                422,
            );
        }

        $this->stateMachine->transition($request, 'supervisor_approved');
        $request->supervisor_id = $supervisorUserId;
        $request->supervisor_remarks = $remarks;
        $request->supervisor_approved_at = now();
        $request->save();

        // Notify department manager that supervisor has endorsed
        $this->notifyManagerOfSupervisorEndorsement($request);

        return $request;
    }

    /**
     * Manager (department-level) final approval.
     *
     * Rules:
     *  - Staff requests: must be supervisor_approved first (ATT-005 enforcement).
     *  - Supervisor requests: can be approved from 'pending' directly.
     *  - Manager requests: must use executiveApprove() instead.
     *
     * ATT-005: OT approval must be made within the grace window after work date.
     * SoD ATT-003: approver must differ from the employee (same user cannot self-approve).
     *
     * @throws SodViolationException|DomainException
     */
    public function approve(
        OvertimeRequest $request,
        int $approvedByUserId,
        ?int $approvedMinutes = null,
        string $remarks = '',
    ): OvertimeRequest {
        // SoD check
        if ($request->employee?->user_id === $approvedByUserId) {
            throw new SodViolationException(
                'overtime_request',
                'approve',
                'Approver must differ from the requesting employee (ATT-003).',
            );
        }

        // Manager requests must go through executiveApprove()
        if ($request->requester_role === 'manager') {
            throw new DomainException(
                'Manager overtime requests must be approved by the Executive. Use executiveApprove().',
                'ATT_OT_USE_EXECUTIVE_APPROVAL',
                422,
            );
        }

        // Staff requests must be endorsed by supervisor first
        if (($request->requester_role === 'staff' || $request->requester_role === null) && $request->status !== 'supervisor_approved') {
            throw new DomainException(
                'Staff overtime requests must be endorsed by the department supervisor first.',
                'ATT_OT_NOT_SUPERVISOR_APPROVED',
                422,
            );
        }

        // Supervisor requests can be approved from 'pending'
        if ($request->requester_role === 'supervisor' && $request->status !== 'pending') {
            throw new DomainException(
                'Supervisor overtime request is not in a reviewable state.',
                'ATT_OT_NOT_PENDING',
                422,
            );
        }

        // ATT-005: Approval must be within the grace window
        $latestApprovalDeadline = Carbon::parse($request->work_date->toDateString())->addDays(7);
        if (Carbon::now()->gt($latestApprovalDeadline)) {
            throw new DomainException(
                sprintf(
                    'Overtime approval deadline has passed. Approvals for %s must be made by %s (ATT-005).',
                    $request->work_date->toDateString(),
                    $latestApprovalDeadline->toDateString(),
                ),
                'ATT_OT_CUTOFF_PASSED',
                422,
            );
        }

        // Approved minutes cannot exceed requested minutes
        $minutes = $approvedMinutes ?? $request->requested_minutes;
        if ($minutes > $request->requested_minutes) {
            throw new DomainException(
                'Approved minutes cannot exceed the requested minutes.',
                'ATT_OT_APPROVED_EXCEEDS_REQUESTED',
                422,
            );
        }

        $this->stateMachine->transition($request, 'manager_checked');
        $request->approved_by = $approvedByUserId;
        $request->approved_minutes = $minutes;
        $request->approver_remarks = $remarks;
        $request->reviewed_at = now();
        $request->save();

        // Employee notification is deferred to vpApprove() — the final approval step.

        return $request;
    }

    /**
     * Officer review step (step 4 of 5).
     * Moves a manager-checked request to officer_reviewed, awaiting VP final approval.
     *
     * @throws SodViolationException|DomainException
     */
    public function officerReview(
        OvertimeRequest $request,
        int $officerUserId,
        string $remarks = '',
    ): OvertimeRequest {
        if ($request->status !== 'manager_checked') {
            throw new DomainException(
                'Overtime request must be manager-checked before officer review.',
                'ATT_OT_NOT_MANAGER_CHECKED',
                422,
            );
        }

        if ($request->employee?->user_id === $officerUserId) {
            throw new SodViolationException(
                'overtime_request',
                'officer_review',
                'Reviewing officer must differ from the requesting employee (ATT-003).',
            );
        }

        $this->stateMachine->transition($request, 'officer_reviewed');
        $request->officer_reviewed_by = $officerUserId;
        $request->officer_reviewed_at = now();
        if ($remarks !== '') {
            $request->approver_remarks = $remarks;
        }
        $request->save();

        return $request;
    }

    /**
     * VP final approval step (step 5 of 5).
     * Moves an officer-reviewed request to approved and notifies the employee.
     *
     * @throws SodViolationException|DomainException
     */
    public function vpApprove(
        OvertimeRequest $request,
        int $vpUserId,
        ?int $approvedMinutes = null,
        string $remarks = '',
    ): OvertimeRequest {
        if ($request->status !== 'officer_reviewed') {
            throw new DomainException(
                'Overtime request must be officer-reviewed before VP approval.',
                'ATT_OT_NOT_OFFICER_REVIEWED',
                422,
            );
        }

        if ($request->employee?->user_id === $vpUserId) {
            throw new SodViolationException(
                'overtime_request',
                'vp_approve',
                'VP approver must differ from the requesting employee (ATT-003).',
            );
        }

        $minutes = $approvedMinutes ?? (int) $request->approved_minutes ?? $request->requested_minutes;
        if ($minutes > $request->requested_minutes) {
            throw new DomainException(
                'Approved minutes cannot exceed the requested minutes.',
                'ATT_OT_APPROVED_EXCEEDS_REQUESTED',
                422,
            );
        }

        $this->stateMachine->transition($request, 'approved');
        $request->vp_approved_by = $vpUserId;
        $request->vp_approved_at = now();
        $request->approved_minutes = $minutes;
        if ($remarks !== '') {
            $request->approver_remarks = $remarks;
        }
        $request->save();

        $this->notifyEmployeeOfOvertimeDecision($request, 'approved', $remarks ?: null);

        return $request;
    }

    /**
     * Executive approval for manager-filed overtime requests.
     * Only users with `overtime.executive_approve` permission can call this.
     *
     * @throws SodViolationException|DomainException
     */
    public function executiveApprove(
        OvertimeRequest $request,
        int $executiveUserId,
        ?int $approvedMinutes = null,
        string $remarks = '',
    ): OvertimeRequest {
        if ($request->requester_role !== 'manager') {
            throw new DomainException(
                'Executive approval is only for manager-filed overtime requests.',
                'ATT_OT_NOT_MANAGER_REQUEST',
                422,
            );
        }

        if ($request->status !== 'pending_executive') {
            throw new DomainException(
                'Request must be pending executive approval.',
                'ATT_OT_NOT_PENDING_EXECUTIVE',
                422,
            );
        }

        // SoD check
        if ($request->employee?->user_id === $executiveUserId) {
            throw new SodViolationException(
                'overtime_request',
                'executive_approve',
                'Executive must differ from the requesting employee (ATT-003).',
            );
        }

        // ATT-005 grace window
        $latestApprovalDeadline = Carbon::parse($request->work_date->toDateString())->addDays(7);
        if (Carbon::now()->gt($latestApprovalDeadline)) {
            throw new DomainException(
                sprintf(
                    'Overtime approval deadline has passed. Approvals for %s must be made by %s (ATT-005).',
                    $request->work_date->toDateString(),
                    $latestApprovalDeadline->toDateString(),
                ),
                'ATT_OT_CUTOFF_PASSED',
                422,
            );
        }

        $minutes = $approvedMinutes ?? $request->requested_minutes;
        if ($minutes > $request->requested_minutes) {
            throw new DomainException(
                'Approved minutes cannot exceed the requested minutes.',
                'ATT_OT_APPROVED_EXCEEDS_REQUESTED',
                422,
            );
        }

        $this->stateMachine->transition($request, 'approved');
        $request->executive_id = $executiveUserId;
        $request->executive_remarks = $remarks;
        $request->executive_approved_at = now();
        $request->approved_by = $executiveUserId;  // Also set as final reviewer
        $request->approved_minutes = $minutes;
        $request->approver_remarks = $remarks;
        $request->reviewed_at = now();
        $request->save();

        $this->notifyEmployeeOfOvertimeDecision($request, 'approved', $remarks ?: null);

        return $request;
    }

    /**
     * Executive rejection for manager-filed overtime requests.
     *
     * @throws SodViolationException|DomainException
     */
    public function executiveReject(
        OvertimeRequest $request,
        int $executiveUserId,
        string $remarks,
    ): OvertimeRequest {
        if ($request->requester_role !== 'manager') {
            throw new DomainException(
                'Executive rejection is only for manager-filed overtime requests.',
                'ATT_OT_NOT_MANAGER_REQUEST',
                422,
            );
        }

        if ($request->status !== 'pending_executive') {
            throw new DomainException(
                'Request must be pending executive approval.',
                'ATT_OT_NOT_PENDING_EXECUTIVE',
                422,
            );
        }

        // SoD check
        if ($request->employee?->user_id === $executiveUserId) {
            throw new SodViolationException(
                'overtime_request',
                'executive_reject',
                'Executive must differ from the requesting employee (ATT-003).',
            );
        }

        $this->stateMachine->transition($request, 'rejected');
        $request->executive_id = $executiveUserId;
        $request->executive_remarks = $remarks;
        $request->executive_approved_at = now();
        $request->approved_by = $executiveUserId;
        $request->approver_remarks = $remarks;
        $request->reviewed_at = now();
        $request->save();

        $this->notifyEmployeeOfOvertimeDecision($request, 'rejected', $remarks);

        return $request;
    }

    /**
     * Reject an overtime request.
     * Can be called by supervisor (from pending) or manager (from pending or supervisor_approved).
     *
     * @throws DomainException
     */
    public function reject(
        OvertimeRequest $request,
        int $reviewedByUserId,
        string $remarks,
    ): OvertimeRequest {
        if (! $request->isPending()) {
            throw new DomainException(
                'Only pending overtime requests can be rejected.',
                'ATT_OT_NOT_PENDING',
                422,
            );
        }

        // If supervisor rejects a staff request while pending, record supervisor rejection
        if ($request->status === 'pending' && $request->requester_role === 'staff') {
            $request->supervisor_id = $reviewedByUserId;
            $request->supervisor_remarks = $remarks;
            $request->supervisor_approved_at = now();
        }

        $this->stateMachine->transition($request, 'rejected');
        $request->approved_by = $reviewedByUserId;
        $request->approver_remarks = $remarks;
        $request->reviewed_at = now();
        $request->save();

        $this->notifyEmployeeOfOvertimeDecision($request, 'rejected', $remarks);

        return $request;
    }

    /**
     * Cancel an overtime request (employee-initiated).
     *
     * @throws DomainException
     */
    public function cancel(OvertimeRequest $request): OvertimeRequest
    {
        if (! $request->isCancellable()) {
            throw new DomainException(
                'Only pending overtime requests can be cancelled.',
                'ATT_OT_NOT_PENDING',
                422,
            );
        }

        // Cannot cancel if the work_date has already passed
        if (Carbon::parse($request->work_date->toDateString())->lt(Carbon::today())) {
            throw new DomainException(
                'Cannot cancel an overtime request after the work date has passed.',
                'ATT_OT_WORK_DATE_PAST',
                422,
            );
        }

        $this->stateMachine->transition($request, 'cancelled');
        $request->save();

        $this->notifyManagerOfOvertimeCancellation($request);

        return $request;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Determine the requester's role from the User model's Spatie roles.
     * Returns 'manager' | 'head' | 'staff'.
     */
    private function resolveRequesterRole(?User $user): string
    {
        if ($user === null) {
            return 'staff';
        }

        if ($user->hasAnyRole(['vice_president', 'officer', 'ga_officer', 'purchasing_officer', 'impex_officer', 'manager', 'plant_manager', 'production_manager', 'qc_manager', 'mold_manager'])) {
            return 'manager';
        }

        if ($user->hasRole('head')) {
            return 'head';
        }

        return 'staff';
    }

    /**
     * Notify the appropriate person that an overtime request has been filed.
     *
     * @param  bool  $skipSupervisor  When true, notify the manager directly
     *                                (used for supervisor-filed OT requests).
     */
    private function notifyManagerOfOvertimeRequest(
        OvertimeRequest $request,
        Employee $employee,
        bool $skipSupervisor = false,
    ): void {
        try {
            $request->loadMissing('employee');

            if (! $skipSupervisor && $employee->reports_to) {
                // Primary: direct supervisor via reports_to (for staff requests)
                $supervisorUserId = Employee::find($employee->reports_to)?->getAttribute('user_id');
                if ($supervisorUserId) {
                    $supervisor = User::find($supervisorUserId);
                    if ($supervisor) {
                        $supervisor->notify(OvertimeRequestedNotification::fromModel($request));

                        return;
                    }
                }
            }

            // Fallback / supervisor OT: notify dept manager
            if ($employee->department_id) {
                User::permission('overtime.approve')
                    ->whereHas('departments', fn ($q) => $q->where('departments.id', $employee->department_id))
                    ->each(fn (User $u) => $u->notify(OvertimeRequestedNotification::fromModel($request)));
            }
        } catch (\Throwable) {
            // Non-fatal — notification failure must not block the submission
        }
    }

    /**
     * Notify the Department Manager after a supervisor has endorsed the request.
     */
    private function notifyManagerOfSupervisorEndorsement(OvertimeRequest $request): void
    {
        try {
            $employee = $request->employee;
            if (! $employee || ! $employee->department_id) {
                return;
            }

            $supervisorName = 'Supervisor';
            if ($request->supervisor_id) {
                $supervisorUser = User::find($request->supervisor_id);
                $supervisorName = $supervisorUser?->name ?? 'Supervisor';
            }

            User::permission('overtime.approve')
                ->whereHas('departments', fn ($q) => $q->where('departments.id', $employee->department_id))
                ->each(function (User $manager) use ($request, $supervisorName): void {
                    $manager->notify(OvertimeSupervisorEndorsedNotification::fromModel(
                        $request->loadMissing('employee'),
                        $supervisorName,
                        $request->supervisor_remarks,
                    ));
                });
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Notify executives when a manager files an overtime request.
     */
    private function notifyExecutiveOfManagerRequest(OvertimeRequest $request, Employee $employee): void
    {
        try {
            $executives = User::role('executive')->get();

            if ($executives->isEmpty()) {
                $executives = User::role('admin')->get();
            }

            foreach ($executives as $executive) {
                $executive->notify(OvertimeManagerRequestedNotification::fromModel($request->loadMissing('employee')));
            }
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Notify the employee that their OT request was approved or rejected.
     */
    private function notifyEmployeeOfOvertimeDecision(
        OvertimeRequest $request,
        string $decision,
        ?string $remarks,
    ): void {
        try {
            $employeeUser = $request->employee?->user_id
                ? User::find($request->employee->user_id)
                : null;

            if ($employeeUser) {
                $employeeUser->notify(OvertimeDecidedNotification::fromModel($request, $decision, $remarks));
            }
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Notify the supervisor (or dept manager fallback) that an OT request was cancelled.
     */
    private function notifyManagerOfOvertimeCancellation(OvertimeRequest $request): void
    {
        try {
            $request->loadMissing('employee');
            $employee = $request->employee;

            if (! $employee) {
                return;
            }

            if ($employee->reports_to) {
                $supervisorUserId = Employee::find($employee->reports_to)?->getAttribute('user_id');
                if ($supervisorUserId) {
                    $supervisor = User::find($supervisorUserId);
                    if ($supervisor) {
                        $supervisor->notify(OvertimeCancelledNotification::fromModel($request));

                        return;
                    }
                }
            }

            if ($employee->department_id) {
                User::permission('overtime.approve')
                    ->whereHas('departments', fn ($q) => $q->where('departments.id', $employee->department_id))
                    ->each(fn (User $u) => $u->notify(OvertimeCancelledNotification::fromModel($request)));
            }
        } catch (\Throwable) {
            // Non-fatal
        }
    }
}
