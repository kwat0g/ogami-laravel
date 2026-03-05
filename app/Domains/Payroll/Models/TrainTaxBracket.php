<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAIN Law (RA 10963) income tax brackets — effective-date versioned.
 *
 * TAX-004: No tax_status_group column. TRAIN Law abolished personal exemptions.
 * A single universal bracket applies to ALL employees regardless of civil status.
 */
final class TrainTaxBracket extends Model
{
    use HasFactory;

    protected $table = 'train_tax_brackets';

    protected $fillable = [
        'effective_date',
        'income_from',
        'income_to',
        'base_tax',
        'excess_rate',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'income_from' => 'decimal:4',
        'income_to' => 'decimal:4',
        'base_tax' => 'decimal:4',
        'excess_rate' => 'decimal:6',
    ];

    /**
     * Scope: Get active brackets for a given date.
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc');
    }

    /**
     * Scope: Get bracket for a specific annual income.
     */
    public function scopeForIncome($query, float $annualIncome)
    {
        return $query->where('income_from', '<=', $annualIncome)
            ->where(fn ($q) => $q->whereNull('income_to')->orWhere('income_to', '>=', $annualIncome));
    }
}
