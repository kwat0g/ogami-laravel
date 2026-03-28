<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Services;

use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\StateMachines\PayrollRunStateMachine;
use App\Domains\Payroll\Validators\PayrollRunValidator;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Traits\HasArchiveOperations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PayrollRun lifecycle service — create, lock, approve, cancel.
 * The actual per-employee computation is handled by PayrollComputationService
 * via the ProcessPayrollBatch queue job.
 */
final class PayrollRunService implements ServiceContract
{
    use HasArchiveOperations;
    public function __construct(
        private readonly PayrollRunStateMachine $machine,
        private readonly PayrollRunValidator $validator,
        private readonly ThirteenthMonthAccrualService $accrualService,
    ) {}

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return PayrollRun::query()
            // Narrow to an explicit set of allowed statuses first (used for
            // role-scoped calls, e.g. Accounting Manager).
            ->when(
                ! empty($filters['statuses']),
                fn ($q) => $q->whereIn('status', $filters['statuses']),
            )
            // Then allow further narrowing to a single status via the filter.
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['year'] ?? null, fn ($q, $y) => $q->whereYear('pay_date', $y))
            ->latest('pay_date')
            ->paginate($perPage);
    }

    /**
     * Create a new draft payroll run.
     *
     * @param  array{cutoff_start: string, cutoff_end: string, pay_date: string, notes?: string}  $data
     *
     * @throws DomainException
     */
    public function create(array $data, int $createdByUserId): PayrollRun
    {
        $runType = $data['run_type'] ?? 'regular';

        $this->validator->assertNonOverlapping(
            $data['cutoff_start'],
            $data['cutoff_end'],
            $runType,
        );
        $this->validator->assertCutoffOrder($data['cutoff_start'], $data['cutoff_end'], $data['pay_date']);

        $cutoffStart = Carbon::parse($data['cutoff_start']);

        $label = $runType === 'thirteenth_month'
            ? $cutoffStart->format('Y').' 13th Month'
            : $this->buildPeriodLabel($cutoffStart);

        $refNo = $this->generateReferenceNo($runType);

        return PayrollRun::create([
            'reference_no' => $refNo,
            'pay_period_label' => $label,
            'pay_period_id' => $data['pay_period_id'] ?? null,
            'cutoff_start' => $data['cutoff_start'],
            'cutoff_end' => $data['cutoff_end'],
            'pay_date' => $data['pay_date'],
            'status' => 'DRAFT',
            'run_type' => $runType,
            'created_by' => $createdByUserId,
            'initiated_by_id' => $createdByUserId,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Lock the run (freezes attendance/leave/loan data). Dispatches batch job.
     *
     * @throws DomainException
     */
    public function lock(PayrollRun $run): PayrollRun
    {
        $this->machine->transition($run, 'locked');

        return $run->fresh();
    }

    /**
     * Mark the run as completed and approved (post-computation).
     *
     * PR-003: SoD — approver must differ from creator.
     *
     * @throws DomainException
     */
    public function approve(PayrollRun $run, int $approvedByUserId): PayrollRun
    {
        if ($run->created_by === $approvedByUserId) {
            throw new DomainException(
                'Payroll run approver must differ from the creator (PR-003 SoD).',
                'PR_SOD_VIOLATION',
                422,
                ['created_by' => $run->created_by, 'approver' => $approvedByUserId],
            );
        }

        $this->machine->transition($run, 'completed');

        $run->approved_by = $approvedByUserId;
        $run->approved_at = now();

        // Roll up totals from detail rows
        $totals = DB::table('payroll_details')
            ->where('payroll_run_id', $run->id)
            ->selectRaw('
                COUNT(*) as emp_count,
                COALESCE(SUM(gross_pay_centavos),0) as gross,
                COALESCE(SUM(total_deductions_centavos),0) as deductions,
                COALESCE(SUM(net_pay_centavos),0) as net
            ')
            ->first();

        $run->total_employees = (int) ($totals->emp_count ?? 0);
        $run->gross_pay_total_centavos = (int) ($totals->gross ?? 0);
        $run->total_deductions_centavos = (int) ($totals->deductions ?? 0);
        $run->net_pay_total_centavos = (int) ($totals->net ?? 0);
        $run->save();

        // 13TH-001: record monthly accruals for regular runs
        if ($run->isRegular()) {
            $this->accrualService->recordForRun($run);
        }

        return $run;
    }

    /**
     * Cancel the run (only allowed from draft or locked).
     *
     * @throws DomainException
     */
    public function cancel(PayrollRun $run): PayrollRun
    {
        $this->machine->transition($run, 'cancelled');

        return $run->fresh();
    }

    /**
     * Soft-archive a payroll run.
     *
     * Only cancelled or rejected runs can be archived.
     *
     * @throws DomainException
     */
    public function archive(PayrollRun $run): PayrollRun
    {
        if ($run->trashed()) {
            return PayrollRun::withTrashed()->findOrFail($run->id);
        }

        if (! ($run->isCancelled() || $run->isRejected())) {
            throw new DomainException(
                sprintf('Payroll run in status "%s" cannot be archived.', $run->status),
                'PR_ARCHIVE_NOT_ALLOWED',
                422,
                ['status' => $run->status],
            );
        }

        return DB::transaction(function () use ($run): PayrollRun {
            $run->delete();

            return PayrollRun::withTrashed()->findOrFail($run->id);
        });
    }

    // ── Restore / Force Delete / List Archived ─────────────────────────────

    public function restoreRun(int $id, User $user): PayrollRun
    {
        /** @var PayrollRun */
        return $this->restoreRecord(PayrollRun::class, $id, $user);
    }

    public function forceDeleteRun(int $id, User $user): void
    {
        $this->forceDeleteRecord(PayrollRun::class, $id, $user);
    }

    public function listArchived(int $perPage = 20): \Illuminate\Pagination\LengthAwarePaginator
    {
        return PayrollRun::onlyTrashed()
            ->with('payPeriod')
            ->orderByDesc('deleted_at')
            ->paginate($perPage);
    }

    /**
     * HR Manager submits the computed run for Accounting Manager review.
     * Transitions: completed → submitted.
     *
     * @throws DomainException
     */
    public function submit(PayrollRun $run, int $submittedByUserId): PayrollRun
    {
        $this->machine->transition($run, 'submitted');

        $run->submitted_by = $submittedByUserId;
        $run->submitted_at = now();
        $run->save();

        return $run->fresh();
    }

    /**
     * Accounting Manager approves the submitted run.
     * Transitions: submitted → approved.
     *
     * SoD (PR-003): approver must differ from the run creator.
     *
     * @throws DomainException
     */
    public function accountingApprove(PayrollRun $run, int $approvedByUserId): PayrollRun
    {
        if ($run->created_by === $approvedByUserId) {
            throw new DomainException(
                'Payroll run approver must differ from the creator (PR-003 SoD).',
                'PR_SOD_VIOLATION',
                422,
                ['created_by' => $run->created_by, 'approver' => $approvedByUserId],
            );
        }

        $this->machine->transition($run, 'approved');

        $run->approved_by = $approvedByUserId;
        $run->approved_at = now();
        $run->save();

        return $run->fresh();
    }

    /**
     * Post the approved run to the General Ledger.
     * Transitions: approved → posted (terminal).
     *
     * @throws DomainException
     */
    public function post(PayrollRun $run): PayrollRun
    {
        $this->machine->transition($run, 'posted');

        return $run->fresh();
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function buildPeriodLabel(Carbon $cutoffStart): string
    {
        $suffix = $cutoffStart->day <= 15 ? '1st' : '2nd';

        return $cutoffStart->format('M Y').' '.$suffix;
    }

    private function generateReferenceNo(string $runType = 'regular'): string
    {
        $year = now()->year;
        $prefix = $runType === 'thirteenth_month' ? 'PR13M' : 'PR';

        $last = PayrollRun::withTrashed()
            ->where('run_type', $runType)
            ->whereYear('created_at', $year)
            ->count();

        return sprintf('%s-%d-%06d', $prefix, $year, $last + 1);
    }
}
