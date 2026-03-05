<?php

declare(strict_types=1);

namespace App\Domains\Loan\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $category government|company
 * @property string|null $description
 * @property float $interest_rate_annual 0.00–1.00 (e.g. 0.10 = 10%)
 * @property int $max_term_months
 * @property int|null $max_amount_centavos null = no cap
 * @property int $min_amount_centavos
 * @property bool $subject_to_min_wage_protection LN-007
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Collection<int, Loan> $loans
 */
final class LoanType extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'loan_types';

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'interest_rate_annual',
        'max_term_months',
        'max_amount_centavos',
        'min_amount_centavos',
        'subject_to_min_wage_protection',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'interest_rate_annual' => 'float',
            'max_term_months' => 'integer',
            'max_amount_centavos' => 'integer',
            'min_amount_centavos' => 'integer',
            'subject_to_min_wage_protection' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Annual rate as percentage string e.g. "10.00%". */
    public function interestRatePercent(): string
    {
        return number_format($this->interest_rate_annual * 100, 2).'%';
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
