<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * SSS contribution table — Monthly Salary Credit (MSC) brackets, effective-date versioned.
 *
 * SSS-001: MSC is looked up by bracket, not computed directly from salary.
 * SSS-005: If salary exceeds the maximum MSC, use the highest row.
 */
final class SssContributionTable extends Model
{
    use HasFactory;

    protected $table = 'sss_contribution_tables';

    protected $fillable = [
        'effective_date',
        'salary_range_from',
        'salary_range_to',
        'monthly_salary_credit',
        'employee_contribution',
        'employer_contribution',
        'ec_contribution',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'salary_range_from' => 'decimal:2',
        'salary_range_to' => 'decimal:2',
        'monthly_salary_credit' => 'decimal:2',
        'employee_contribution' => 'decimal:2',
        'employer_contribution' => 'decimal:2',
        'ec_contribution' => 'decimal:2',
    ];

    /**
     * Scope: Get active contributions for a given date.
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc');
    }

    /**
     * Scope: Get bracket for a specific monthly salary.
     */
    public function scopeForSalary($query, float $monthlySalary)
    {
        return $query->where('salary_range_from', '<=', $monthlySalary)
            ->where(fn ($q) => $q->whereNull('salary_range_to')->orWhere('salary_range_to', '>=', $monthlySalary));
    }
}
