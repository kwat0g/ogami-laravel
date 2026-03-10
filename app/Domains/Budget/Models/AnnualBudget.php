<?php

declare(strict_types=1);

namespace App\Domains\Budget\Models;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $ulid
 * @property int $cost_center_id
 * @property int $fiscal_year
 * @property int $account_id
 * @property int $budgeted_amount_centavos
 * @property string|null $notes
 * @property int $created_by_id
 * @property int|null $updated_by_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read CostCenter $costCenter
 * @property-read ChartOfAccount $account
 * @property-read User $createdBy
 * @property-read User|null $updatedBy
 */
final class AnnualBudget extends Model
{
    use HasPublicUlid;
    use SoftDeletes;

    protected $table = 'annual_budgets';

    protected $fillable = [
        'cost_center_id',
        'fiscal_year',
        'account_id',
        'budgeted_amount_centavos',
        'notes',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'fiscal_year'               => 'integer',
        'budgeted_amount_centavos'  => 'integer',
    ];

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
