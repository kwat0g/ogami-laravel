<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $night_start_time HH:MM:SS
 * @property string $night_end_time HH:MM:SS
 * @property int $differential_rate_bps basis points (1000 = 10%)
 * @property Carbon $effective_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class NightShiftConfig extends Model
{
    protected $table = 'night_shift_configs';

    /** @var list<string> */
    protected $fillable = [
        'night_start_time',
        'night_end_time',
        'differential_rate_bps',
        'effective_date',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'differential_rate_bps' => 'integer',
        ];
    }

    /**
     * Get the config effective on a given date.
     */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->where('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->limit(1);
    }
}
