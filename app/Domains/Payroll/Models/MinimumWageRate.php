<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimum wage rates per region — effective-date versioned.
 *
 * EMP-012: Employee basic_salary must be >= prevailing minimum wage for their region.
 * DED-001/LN-007: Minimum wage check before applying voluntary/loan deductions.
 */
final class MinimumWageRate extends Model
{
    use HasFactory;

    protected $table = 'minimum_wage_rates';

    protected $fillable = [
        'effective_date',
        'region',
        'daily_rate',
        'wage_order_reference',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'daily_rate' => 'decimal:2',
    ];

    /**
     * Scope: Get active rates for a given date.
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc');
    }

    /**
     * Scope: Get rate for a specific region.
     */
    public function scopeForRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    /**
     * Get all available regions.
     */
    public static function getRegions(): array
    {
        return self::distinct()->pluck('region')->toArray();
    }
}
