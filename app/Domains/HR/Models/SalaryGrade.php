<?php

declare(strict_types=1);

namespace App\Domains\HR\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $level 1–20
 * @property string $employment_type regular|contractual|project_based|seasonal|probationary
 * @property int $min_monthly_rate centavos
 * @property int $max_monthly_rate centavos
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Employee> $employees
 */
final class SalaryGrade extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $table = 'salary_grades';

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'level',
        'employment_type',
        'min_monthly_rate',
        'max_monthly_rate',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'min_monthly_rate' => 'integer',
            'max_monthly_rate' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Min monthly rate in full pesos. */
    public function getMinMonthlyRatePesosAttribute(): float
    {
        return $this->min_monthly_rate / 100;
    }

    /** Max monthly rate in full pesos. */
    public function getMaxMonthlyRatePesosAttribute(): float
    {
        return $this->max_monthly_rate / 100;
    }

    /** Whether the given centavo amount falls within this grade's range. */
    public function inRange(int $centavos): bool
    {
        return $centavos >= $this->min_monthly_rate && $centavos <= $this->max_monthly_rate;
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
