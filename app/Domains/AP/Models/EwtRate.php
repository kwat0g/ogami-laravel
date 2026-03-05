<?php

declare(strict_types=1);

namespace App\Domains\AP\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * EWT Rate — effective-dated ATC rate lookup.
 *
 * @property int $id
 * @property string $atc_code
 * @property string $description
 * @property float $rate e.g. 0.01 = 1%
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property bool $is_active
 */
final class EwtRate extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'ewt_rates';

    protected $fillable = [
        'atc_code',
        'description',
        'rate',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    // ── Scopes ───────────────────────────────────────────────────────────────

    /** Active rates for a given ATC code effective on a specific date (EWT-001). */
    public function scopeEffectiveOn(Builder $query, string $atcCode, Carbon $date): Builder
    {
        return $query
            ->where('atc_code', $atcCode)
            ->where('effective_from', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
