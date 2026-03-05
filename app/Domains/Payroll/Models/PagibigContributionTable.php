<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pag-IBIG (HDMF) contribution table — effective-date versioned.
 *
 * PAGIBIG-002: Employee rate = 1% if monthly_basic ≤ salary_threshold; 2% if above.
 * PAGIBIG-003: Employee contribution is CAPPED at employee_cap_monthly (₱100/month).
 * PAGIBIG-004: Employer rate = 2%, no cap.
 */
final class PagibigContributionTable extends Model
{
    use HasFactory;

    protected $table = 'pagibig_contribution_tables';

    protected $fillable = [
        'effective_date',
        'salary_threshold',
        'employee_rate_below',
        'employee_rate_above',
        'employee_cap_monthly',
        'employer_rate',
        'legal_basis',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'salary_threshold' => 'decimal:2',
        'employee_rate_below' => 'decimal:6',
        'employee_rate_above' => 'decimal:6',
        'employee_cap_monthly' => 'decimal:2',
        'employer_rate' => 'decimal:6',
    ];

    /**
     * Scope: Get active contribution rate for a given date.
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc');
    }
}
