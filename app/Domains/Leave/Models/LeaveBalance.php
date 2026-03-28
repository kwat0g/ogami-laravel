<?php

declare(strict_types=1);

namespace App\Domains\Leave\Models;

use App\Domains\HR\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Per-employee, per-leave-type, per-year balance ledger.
 *
 * `balance` is a PostgreSQL stored computed column:
 *   opening_balance + accrued + adjusted - used - monetized
 *
 * LV-001: Balance must never go negative (enforced by DB CHECK).
 * LV-002: Carry-over caps enforced by LeaveAccrualService at year-end.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $year 2020–2100
 * @property float $opening_balance days
 * @property float $accrued days added via monthly accrual
 * @property float $adjusted manual adjustments (positive or negative)
 * @property float $used days consumed by approved leave requests
 * @property float $monetized days converted to cash payout (LV-007)
 * @property float $balance stored computed = opening + accrued + adjusted - used - monetized
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee $employee
 * @property-read LeaveType $leaveType
 */
final class LeaveBalance extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $table = 'leave_balances';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'opening_balance',
        'accrued',
        'adjusted',
        'used',
        'monetized',
    ];

    /** @var list<string> Stored computed — never write directly. */
    protected $guarded = ['balance'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'opening_balance' => 'float',
            'accrued' => 'float',
            'adjusted' => 'float',
            'used' => 'float',
            'monetized' => 'float',
            'balance' => 'float',
        ];
    }

    /** Whether the employee has enough balance for the given number of days. */
    public function hasSufficientBalance(float $requestedDays): bool
    {
        return $this->balance >= $requestedDays;
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
