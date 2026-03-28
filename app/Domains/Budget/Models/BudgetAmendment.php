<?php

declare(strict_types=1);

namespace App\Domains\Budget\Models;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Budget Amendment — mid-year budget revision with approval workflow.
 *
 * Types:
 *   - reallocation: move budget from one GL account to another (zero-sum within cost center)
 *   - increase: request additional budget allocation (increases total)
 *   - decrease: voluntarily reduce budget line
 *
 * Workflow: draft -> submitted -> approved | rejected
 *
 * @property int $id
 * @property string $ulid
 * @property int $cost_center_id
 * @property int $fiscal_year
 * @property string $amendment_type reallocation|increase|decrease
 * @property int|null $source_account_id GL account to take budget FROM (reallocation)
 * @property int $target_account_id GL account to add budget TO
 * @property int $amount_centavos
 * @property string $justification
 * @property string $status draft|submitted|approved|rejected
 * @property int $requested_by_id
 * @property int|null $approved_by_id
 * @property Carbon|null $approved_at
 * @property string|null $approval_remarks
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class BudgetAmendment extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'budget_amendments';

    protected $fillable = [
        'cost_center_id',
        'fiscal_year',
        'amendment_type',
        'source_account_id',
        'target_account_id',
        'amount_centavos',
        'justification',
        'status',
        'requested_by_id',
        'approved_by_id',
        'approved_at',
        'approval_remarks',
        'created_by_id',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'amount_centavos' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'source_account_id');
    }

    public function targetAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'target_account_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
