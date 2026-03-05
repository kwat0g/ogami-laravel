<?php

declare(strict_types=1);

namespace App\Domains\Loan\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * One row per loan installment.
 *
 * LN-007: When deducting this installment would push net pay below the
 *         minimum wage, is_protected_by_min_wage is set to true and the
 *         deduction is suspended for that payroll period (status = 'skipped').
 *         Protected installments must be rescheduled.
 *
 * @property int $id
 * @property int $loan_id
 * @property int $installment_no 1-based
 * @property \Illuminate\Support\Carbon $due_date
 * @property int $principal_portion_centavos
 * @property int $interest_portion_centavos
 * @property int $total_due_centavos = principal + interest (DB CHECK)
 * @property int $paid_centavos default 0
 * @property string $status pending|paid|skipped|protected
 * @property bool $is_protected_by_min_wage LN-007
 * @property int|null $payroll_run_id
 * @property \Illuminate\Support\Carbon|null $paid_date
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Loan $loan
 */
final class LoanAmortizationSchedule extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected static function newFactory(): \Database\Factories\LoanAmortizationScheduleFactory
    {
        return \Database\Factories\LoanAmortizationScheduleFactory::new();
    }

    protected $table = 'loan_amortization_schedules';

    /** @var list<string> */
    protected $fillable = [
        'loan_id',
        'installment_no',
        'due_date',
        'principal_portion_centavos',
        'interest_portion_centavos',
        'total_due_centavos',
        'paid_centavos',
        'status',
        'is_protected_by_min_wage',
        'payroll_run_id',
        'paid_date',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_date' => 'date',
            'installment_no' => 'integer',
            'principal_portion_centavos' => 'integer',
            'interest_portion_centavos' => 'integer',
            'total_due_centavos' => 'integer',
            'paid_centavos' => 'integer',
            'is_protected_by_min_wage' => 'boolean',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_date->isPast();
    }

    /** Remaining unpaid amount in centavos. */
    public function remainingCentavos(): int
    {
        return $this->total_due_centavos - $this->paid_centavos;
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
