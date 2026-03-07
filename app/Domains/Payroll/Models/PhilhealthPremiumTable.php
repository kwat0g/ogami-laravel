<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PhilHealth premium contribution table — effective-date versioned.
 *
 * PHL-002: Premium = basic_salary × premium_rate. Base = basic_salary ONLY.
 * PHL-003: Both employee and employer share = premium_rate / 2.
 * PHL-004: Semi-monthly employee deduction = (basic_salary × premium_rate / 2) / 2.
 */
final class PhilhealthPremiumTable extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'philhealth_premium_tables';

    protected $fillable = [
        'effective_date',
        'salary_floor',
        'salary_ceiling',
        'premium_rate',
        'min_monthly_premium',
        'max_monthly_premium',
        'legal_basis',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'salary_floor' => 'decimal:2',
        'salary_ceiling' => 'decimal:2',
        'premium_rate' => 'decimal:6',
        'min_monthly_premium' => 'decimal:2',
        'max_monthly_premium' => 'decimal:2',
    ];

    /**
     * Scope: Get active premium rate for a given date.
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc');
    }
}
