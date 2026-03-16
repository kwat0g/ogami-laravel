<?php

declare(strict_types=1);

namespace App\Domains\Loan\Models;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Loan header.  Amortization schedule lives in loan_amortization_schedules.
 *
 * State machine:  pending → approved → active → fully_paid
 *                          ↘ cancelled
 *                 active → written_off
 *
 * SoD: approved_by <> requested_by enforced at DB level (LN-SoD).
 *
 * @property int $id
 * @property int $workflow_version 1 = legacy 3-stage, 2 = new 5-stage
 * @property string $reference_no LN-YYYY-NNNNNN
 * @property int $employee_id
 * @property int $loan_type_id
 * @property int $requested_by FK users.id
 * @property int $principal_centavos > 0
 * @property int $term_months 1–120
 * @property float $interest_rate_annual snapshot from loan_type at time of approval
 * @property int $monthly_amortization_centavos
 * @property int $total_payable_centavos
 * @property string $status pending|head_noted|manager_checked|officer_reviewed|supervisor_approved|approved|ready_for_disbursement|active|fully_paid|cancelled|written_off
 * @property int|null $head_noted_by FK users.id
 * @property string|null $head_remarks
 * @property Carbon|null $head_noted_at
 * @property int|null $manager_checked_by FK users.id
 * @property string|null $manager_remarks
 * @property Carbon|null $manager_checked_at
 * @property int|null $officer_reviewed_by FK users.id
 * @property string|null $officer_remarks
 * @property Carbon|null $officer_reviewed_at
 * @property int|null $vp_approved_by FK users.id
 * @property string|null $vp_remarks
 * @property Carbon|null $vp_approved_at
 * @property int|null $approved_by FK users.id — must != requested_by (HR/Manager approval)
 * @property string|null $approver_remarks
 * @property Carbon|null $approved_at
 * @property int|null $supervisor_approved_by FK users.id — Supervisor approval
 * @property string|null $supervisor_remarks
 * @property Carbon|null $supervisor_approved_at
 * @property int|null $accounting_approved_by FK users.id — Accounting Manager approval
 * @property string|null $accounting_remarks
 * @property Carbon|null $accounting_approved_at
 * @property int|null $journal_entry_id FK to journal_entries for GL tracking
 * @property Carbon|null $disbursed_at
 * @property int|null $disbursed_by FK users.id — who released the funds
 * @property string|null $loan_date
 * @property Carbon|null $first_deduction_date
 * @property string|null $purpose
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee $employee
 * @property-read LoanType $loanType
 * @property-read Collection<int, LoanAmortizationSchedule> $amortizationSchedules
 */
final class Loan extends Model implements Auditable
{
    use AuditableTrait, HasFactory, HasPublicUlid, SoftDeletes;

    protected static function newFactory(): LoanFactory
    {
        return LoanFactory::new();
    }

    protected $table = 'loans';

    /** @var list<string> */
    protected $fillable = [
        'workflow_version',
        'reference_no',
        'employee_id',
        'loan_type_id',
        'requested_by',
        'principal_centavos',
        'term_months',
        'interest_rate_annual',
        'monthly_amortization_centavos',
        'total_payable_centavos',
        'outstanding_balance_centavos',
        'loan_date',
        'first_deduction_date',
        'deduction_cutoff',
        'status',
        // v2 workflow actors
        'head_noted_by',
        'head_noted_at',
        'head_remarks',
        'manager_checked_by',
        'manager_checked_at',
        'manager_remarks',
        'officer_reviewed_by',
        'officer_reviewed_at',
        'officer_remarks',
        'vp_approved_by',
        'vp_approved_at',
        'vp_remarks',
        // v1 legacy actors
        'approved_by',
        'approver_remarks',
        'approved_at',
        'supervisor_approved_by',
        'supervisor_remarks',
        'supervisor_approved_at',
        'accounting_approved_by',
        'accounting_remarks',
        'accounting_approved_at',
        'journal_entry_id',
        'disbursed_at',
        'disbursed_by',
        'purpose',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'workflow_version' => 'integer',
            'principal_centavos' => 'integer',
            'term_months' => 'integer',
            'interest_rate_annual' => 'float',
            'monthly_amortization_centavos' => 'integer',
            'total_payable_centavos' => 'integer',
            'outstanding_balance_centavos' => 'integer',
            'loan_date' => 'date',
            'first_deduction_date' => 'date',
            // v2 workflow timestamps
            'head_noted_at' => 'datetime',
            'manager_checked_at' => 'datetime',
            'officer_reviewed_at' => 'datetime',
            'vp_approved_at' => 'datetime',
            // v1 legacy timestamps
            'approved_at' => 'datetime',
            'supervisor_approved_at' => 'datetime',
            'accounting_approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    // ── State helpers ─────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeCancelled(): bool
    {
        // Cancellable only before Manager approval (v1 or v2)
        return in_array($this->status, ['pending', 'supervisor_approved', 'head_noted'], true);
    }

    // ── Monetary helpers ──────────────────────────────────────────────────────

    public function principalPesos(): float
    {
        return $this->principal_centavos / 100;
    }

    public function monthlyAmortizationPesos(): float
    {
        return $this->monthly_amortization_centavos / 100;
    }

    /** Outstanding principal based on paid amortizations. */
    public function outstandingBalance(): int
    {
        return $this->amortizationSchedules()
            ->where('status', '!=', 'paid')
            ->sum('principal_portion_centavos');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function accountingApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accounting_approved_by');
    }

    // v2 workflow relations
    public function headNotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_noted_by');
    }

    public function managerCheckedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_checked_by');
    }

    public function officerReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_reviewed_by');
    }

    public function vpApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vp_approved_by');
    }

    public function amortizationSchedules(): HasMany
    {
        return $this->hasMany(LoanAmortizationSchedule::class)->orderBy('installment_no');
    }

    /** Next unpaid installment. */
    public function nextInstallment(): ?LoanAmortizationSchedule
    {
        /** @var LoanAmortizationSchedule|null $next */
        $next = $this->amortizationSchedules()
            ->whereIn('status', ['pending', 'skipped'])
            ->orderBy('installment_no')
            ->first();

        return $next;
    }
}
