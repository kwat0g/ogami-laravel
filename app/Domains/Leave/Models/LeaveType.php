<?php

declare(strict_types=1);

namespace App\Domains\Leave\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $code SL|VL|SIL|ML|PL|SPL|VAWCL|LWOP
 * @property string $name
 * @property string $category sick|vacation|service_incentive|maternity|paternity|
 *                            solo_parent|vawc|lwop|other
 * @property bool $is_paid
 * @property int $max_days_per_year
 * @property bool $requires_approval
 * @property bool $requires_documentation
 * @property float|null $monthly_accrual_days null = granted as lump-sum on Jan 1
 * @property int $max_carry_over_days 0 = no carry-over
 * @property bool $can_be_monetized SIL — LV-007
 * @property bool $deducts_absent_on_lwop LWOP — LV-006
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, LeaveBalance> $balances
 * @property-read Collection<int, LeaveRequest> $requests
 */
final class LeaveType extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes, SoftDeletes;

    protected $table = 'leave_types';

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'category',
        'is_paid',
        'max_days_per_year',
        'requires_approval',
        'requires_documentation',
        'monthly_accrual_days',
        'max_carry_over_days',
        'can_be_monetized',
        'deducts_absent_on_lwop',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'max_days_per_year' => 'integer',
            'requires_approval' => 'boolean',
            'requires_documentation' => 'boolean',
            'monthly_accrual_days' => 'float',
            'max_carry_over_days' => 'integer',
            'can_be_monetized' => 'boolean',
            'deducts_absent_on_lwop' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
