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

// NOTICE: This file has been rewritten to implement the 4-step approval chain
// matching physical form AD-084-00 (Leave of Absence Request Form).
// Old methods (supervisorApprove, approve, executiveApprove, executiveReject)
// are replaced by headApprove, managerCheck, gaProcess, vpNote.

/**
 * Leave request lifecycle service — 4-step approval chain (form AD-084-00).
 *
 * Business rules:
 *  LV-001: Cannot approve_with_pay if balance would go negative
 *  LV-004: Each approver must differ from submitted_by (SoD)
 *  LV-005: Rejected/cancelled requests do not deduct balance
 *  LV-006: LWOP always approved_without_pay; no balance check
 *  LV-008: Half-day requests count as 0.5 days
 *
 * Workflow:
 *   submitted → head_approved → manager_checked → ga_processed → approved
 *                                              ↘ rejected  (action_taken = disapproved)
 */
final class LeaveRequestService implements ServiceContract
{
    // ── Step 1 — Employee submits ─────────────────────────────────────────────

    /**
     * Submit a leave request for approval.
     * All employees (regardless of role) go to status 'submitted'.
     * Balance check is deferred to the GA Officer step.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    public function submit(
        Employee $employee,
        array $data,
        int $submittedByUserId
    ): LeaveRequest {
        $request = DB::transaction(function () use ($employee, $data, $submittedByUserId): LeaveRequest {
            $leaveType = LeaveType::findOrFail($data['leave_type_id']);

            $isHalfDay = isset($data['is_half_day']) && $data['is_half_day'];

            if ($isHalfDay) {
                $totalDays = 0.5;
            } elseif (isset($data['total_days']) && $data['total_days'] > 0) {
                $totalDays = (float) $data['total_days'];
            } else {
                // Compute inclusive calendar days from date range
                $from = new \Carbon\Carbon($data['date_from']);
                $to   = new \Carbon\Carbon($data['date_to']);
                $totalDays = (float) ($from->diffInDays($to) + 1);
            }

            return LeaveRequest::create([
                'employee_id'     => $employee->id,
                'leave_type_id'   => $leaveType->id,
                'submitted_by'    => $submittedByUserId,
                'date_from'       => $data['date_from'],
                'date_to'         => $data['date_to'],
                'total_days'      => $totalDays,
                'is_half_day'     => $isHalfDay,
                'half_day_period' => isset($data['half_day_period']) ? strtolower($data['half_day_period']) : null,
                'reason'          => $data['reason'],
                'status'          => 'submitted',
            ]);
        });

        // Notify department heads in the employee's department
        $this->notifyHeadsOfFiledRequest($request, $employee);

        return $request;
    }

    // ── Step 2 — Department Head approves ────────────────────────────────────

    /**
     * Department Head approves the request.
     * Moves: submitted → head_approved.
     *
     * @throws SodViolationException|DomainException
     */
    public function headApprove(LeaveRequest $request, int $headUserId, ?string $remarks = null): LeaveRequest
    {
        if ($request->submitted_by === $headUserId) {
            throw new SodViolationException('leave_request', 'head_approve', 'Head must differ from submitter (LV-004).');
        }

        if ($request->status !== 'submitted') {
            throw new DomainException('Only submitted requests can be head-approved.', 'LV_NOT_SUBMITTED', 422);
        }

        $request->status           = 'head_approved';
        $request->head_id          = $headUserId;
        $request->head_remarks     = $remarks;
        $request->head_approved_at = now();
        $request->save();

        $this->notifyManagerOfHeadApproval($request);

        return $request;
    }

    // ── Step 3 — Plant Manager checks ────────────────────────────────────────

    /**
     * Plant Manager checks the request.
     * Moves: head_approved → manager_checked.
     *
     * @throws SodViolationException|DomainException
     */
    public function managerCheck(LeaveRequest $request, int $managerUserId, ?string $remarks = null): LeaveRequest
    {
        if ($request->submitted_by === $managerUserId) {
            throw new SodViolationException('leave_request', 'manager_check', 'Manager must differ from submitter (LV-004).');
        }

        if ($request->status !== 'head_approved') {
            throw new DomainException('Only head-approved requests can be manager-checked.', 'LV_NOT_HEAD_APPROVED', 422);
        }

        $request->status               = 'manager_checked';
        $request->manager_checked_by   = $managerUserId;
        $request->manager_check_remarks = $remarks;
        $request->manager_checked_at   = now();
        $request->save();

        $this->notifyGaOfficerOfManagerCheck($request);

        return $request;
    }

    // ── Step 4 — GA Officer processes ────────────────────────────────────────

    /**
     * GA Officer receives and classifies the request.
     *
     * Sets action_taken:
     *  - 'approved_with_pay'    → captures balance snapshot → moves to ga_processed → VP notes
     *  - 'approved_without_pay' → marks as LWOP; no balance deduction → moves to ga_processed → VP notes
     *  - 'disapproved'          → immediately rejected; VP step skipped
     *
     * @throws SodViolationException|DomainException|InsufficientLeaveBalanceException
     */
    public function gaProcess(
        LeaveRequest $request,
        int $gaUserId,
        string $actionTaken,
        ?string $remarks = null
    ): LeaveRequest {
        if ($request->submitted_by === $gaUserId) {
            throw new SodViolationException('leave_request', 'ga_process', 'GA Officer must differ from submitter (LV-004).');
        }

        if ($request->status !== 'manager_checked') {
            throw new DomainException('Only manager-checked requests can be GA-processed.', 'LV_NOT_MANAGER_CHECKED', 422);
        }

        if (! in_array($actionTaken, ['approved_with_pay', 'approved_without_pay', 'disapproved'], true)) {
            throw new DomainException('Invalid action_taken value.', 'LV_INVALID_ACTION_TAKEN', 422);
        }

        $request = DB::transaction(function () use ($request, $gaUserId, $actionTaken, $remarks): LeaveRequest {
            $leaveType = $request->leaveType;
            $year      = $request->date_from->year;

            $request->ga_processed_by = $gaUserId;
            $request->ga_remarks      = $remarks;
            $request->ga_processed_at = now();
            $request->action_taken    = $actionTaken;

            if ($actionTaken === 'disapproved') {
                // Immediate rejection — VP step is skipped
                $request->status = 'rejected';
            } else {
                // Capture balance snapshot — LV-001 check at this step
                if ($actionTaken === 'approved_with_pay') {
                    // LV-006: OTH is discretionary — no balance record; skip balance check
                    if ($leaveType->code === 'OTH') {
                        $request->beginning_balance = null;
                        $request->applied_days      = 0;
                        $request->ending_balance    = null;
                    } else {
                        $balance = LeaveBalance::where([
                            'employee_id'   => $request->employee_id,
                            'leave_type_id' => $request->leave_type_id,
                            'year'          => $year,
                        ])->lockForUpdate()->first();

                        // @phpstan-ignore nullsafe.neverNull
                        $currentBalance = $balance?->balance ?? 0.0;

                        if ($currentBalance < $request->total_days) {
                            throw new InsufficientLeaveBalanceException(
                                $leaveType->name,
                                $request->total_days,
                                $currentBalance,
                            );
                        }

                        $request->beginning_balance = $currentBalance;
                        $request->applied_days      = $request->total_days;
                        $request->ending_balance    = $currentBalance - $request->total_days;
                    }
                } else {
                    // approved_without_pay — snapshot zeros
                    $request->beginning_balance = null;
                    $request->applied_days      = 0;
                    $request->ending_balance    = null;
                }

                $request->status = 'ga_processed';
            }

            $request->save();

            return $request;
        });

        if ($request->status === 'rejected') {
            $this->notifyEmployeeOfDecision($request, 'rejected', $remarks);
        } else {
            $this->notifyVpOfGaApproval($request);
        }

        return $request;
    }

    // ── Step 5 — VP notes ─────────────────────────────────────────────────────

    /**
     * Vice President notes the request (final step).
     * Moves: ga_processed → approved.
     * Deducts balance only when action_taken = 'approved_with_pay'.
     *
     * @throws SodViolationException|DomainException|InsufficientLeaveBalanceException
     */
    public function vpNote(LeaveRequest $request, int $vpUserId, ?string $remarks = null): LeaveRequest
    {
        if ($request->submitted_by === $vpUserId) {
            throw new SodViolationException('leave_request', 'vp_note', 'VP must differ from submitter (LV-004).');
        }

        if ($request->status !== 'ga_processed') {
            throw new DomainException('Only GA-processed requests can be VP-noted.', 'LV_NOT_GA_PROCESSED', 422);
        }

        $request = DB::transaction(function () use ($request, $vpUserId, $remarks): LeaveRequest {
            // Deduct balance only for approved_with_pay
            if ($request->action_taken === 'approved_with_pay') {
                $year = $request->date_from->year;

                $balance = LeaveBalance::where([
                    'employee_id'   => $request->employee_id,
                    'leave_type_id' => $request->leave_type_id,
                    'year'          => $year,
                ])->lockForUpdate()->first();

                if ($balance !== null) {
                    // Re-validate balance hasn't changed since GA snapshot
                    if ($balance->balance < $request->total_days) {
                        throw new InsufficientLeaveBalanceException(
                            $request->leaveType->name,
                            $request->total_days,
                            $balance->balance,
                        );
                    }

                    $balance->used += $request->total_days;
                    $balance->save();
                }
            }

            $request->status      = 'approved';
            $request->vp_id       = $vpUserId;
            $request->vp_remarks  = $remarks;
            $request->vp_noted_at = now();
            $request->save();

            return $request;
        });

        $this->notifyEmployeeOfDecision($request, 'approved', $remarks);

        return $request;
    }

    // ── Reject at any pending step ─────────────────────────────────────────────

    /**
     * Reject a leave request — balance is NOT deducted.
     * Any approver (head, manager, GA, VP) can reject at their step.
     *
     * @throws SodViolationException|DomainException
     */
    public function reject(LeaveRequest $request, int $rejectedByUserId, string $remarks): LeaveRequest
    {
        if ($request->submitted_by === $rejectedByUserId) {
            throw new SodViolationException('leave_request', 'reject', 'Rejector must differ from submitter (LV-004).');
        }

        if (! $request->isPending()) {
            throw new DomainException('Only pending requests can be rejected.', 'LV_NOT_PENDING', 422);
        }

        // Record who rejected at each possible step
        match ($request->status) {
            'submitted'       => $request->fill([
                'head_id'         => $rejectedByUserId,
                'head_remarks'    => $remarks,
                'head_approved_at' => now(),
            ]),
            'head_approved'   => $request->fill([
                'manager_checked_by'    => $rejectedByUserId,
                'manager_check_remarks' => $remarks,
                'manager_checked_at'    => now(),
            ]),
            'manager_checked' => $request->fill([
                'ga_processed_by' => $rejectedByUserId,
                'ga_remarks'      => $remarks,
                'ga_processed_at' => now(),
                'action_taken'    => 'disapproved',
            ]),
            'ga_processed' => $request->fill([
                'vp_id'       => $rejectedByUserId,
                'vp_remarks'  => $remarks,
                'vp_noted_at' => now(),
            ]),
            default => null,
        };

        $request->status = 'rejected';
        $request->save();

        $this->notifyEmployeeOfDecision($request, 'rejected', $remarks);

        return $request;
    }

    // ── Cancel ─────────────────────────────────────────────────────────────────

    /**
     * Cancel a leave request. Only possible while still in draft or submitted.
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

        DB::transaction(function () use ($request): void {
            $request->status = 'cancelled';
            $request->save();
        });

        return $request;
    }

    // ── Private notification helpers ──────────────────────────────────────────

    /**
     * Step 1 → Step 2: Notify department heads that a request has been submitted.
     */
    private function notifyHeadsOfFiledRequest(LeaveRequest $request, Employee $employee): void
    {
        try {
            $request->loadMissing('employee', 'leaveType');

            $heads = User::permission('leaves.head_approve')
                ->whereHas('departments', fn ($q) => $q->where('departments.id', $employee->department_id))
                ->get();

            foreach ($heads as $head) {
                $head->notify(new LeaveFiledNotification($request));
            }

            if ($heads->isNotEmpty()) {
                LeaveRequestFiled::dispatch($request, $heads->first()->id);
            }
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Step 2 → Step 3: Notify plant managers that head has approved.
     */
    private function notifyManagerOfHeadApproval(LeaveRequest $request): void
    {
        try {
            $headUser = User::find($request->head_id);
            $headName = $headUser !== null ? $headUser->name : 'Department Head';

            $managers = User::permission('leaves.manager_check')->get();

            foreach ($managers as $manager) {
                $manager->notify(new LeaveSupervisorEndorsedNotification(
                    $request->loadMissing('employee', 'leaveType'),
                    $headName,
                    $request->head_remarks,
                ));
            }
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Step 3 → Step 4: Notify GA officers that manager has checked.
     */
    private function notifyGaOfficerOfManagerCheck(LeaveRequest $request): void
    {
        try {
            $managerUser = User::find($request->manager_checked_by);
            $managerName = $managerUser !== null ? $managerUser->name : 'Plant Manager';

            $gaOfficers = User::permission('leaves.ga_process')->get();

            foreach ($gaOfficers as $gaOfficer) {
                $gaOfficer->notify(new LeaveSupervisorEndorsedNotification(
                    $request->loadMissing('employee', 'leaveType'),
                    $managerName,
                    $request->manager_check_remarks,
                ));
            }
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Step 4 → Step 5: Notify VP that GA has processed.
     */
    private function notifyVpOfGaApproval(LeaveRequest $request): void
    {
        try {
            $gaUser = User::find($request->ga_processed_by);
            $gaName = $gaUser !== null ? $gaUser->name : 'GA Officer';

            $vps = User::permission('leaves.vp_note')->get();

            foreach ($vps as $vp) {
                $vp->notify(new LeaveSupervisorEndorsedNotification(
                    $request->loadMissing('employee', 'leaveType'),
                    $gaName,
                    $request->ga_remarks,
                ));
            }
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Step 5 or GA disapproval: Notify the employee of the final decision.
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
