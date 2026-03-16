<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Models;

use App\Models\User;
use App\Shared\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Recurring Journal Entry Template — parameterised blueprint that the
 * `journals:generate-recurring` command materialises automatically.
 *
 * @property int $id
 * @property string $ulid
 * @property string $description
 * @property string $frequency daily|weekly|monthly|semi_monthly|annual
 * @property int|null $day_of_month 1–28; relevant for monthly/semi_monthly
 * @property Carbon $next_run_date
 * @property Carbon|null $last_run_at
 * @property bool $is_active
 * @property array<int, array<string, mixed>> $lines [{account_id, debit, credit, description}]
 * @property int $created_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
final class RecurringJournalTemplate extends Model implements Auditable
{
    use AuditableTrait, HasPublicUlid, SoftDeletes;

    protected $table = 'recurring_journal_templates';

    protected $fillable = [
        'description',
        'frequency',
        'day_of_month',
        'next_run_date',
        'last_run_at',
        'is_active',
        'lines',
        'created_by_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'next_run_date' => 'date',
        'last_run_at' => 'datetime',
        'is_active' => 'boolean',
        'lines' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
