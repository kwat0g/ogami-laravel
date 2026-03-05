<?php

declare(strict_types=1);

namespace App\Domains\HR\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

/**
 * UniqueGovernmentIdRule — validates that SSS/GSIS/TIN/Pag-IBIG/PhilHealth
 * numbers are unique across all active employees.
 *
 * Usage (in a FormRequest):
 *   'sss_number' => [new UniqueGovernmentIdRule('sss_number', $currentEmployeeId)]
 */
final class UniqueGovernmentIdRule implements Rule
{
    public function __construct(
        private readonly string $column,
        private readonly ?int $excludeEmployeeId = null,
    ) {}

    public function passes(mixed $attribute, mixed $value): bool
    {
        $query = DB::table('employees')
            ->whereNull('deleted_at')
            ->where($this->column, $value);

        if ($this->excludeEmployeeId !== null) {
            $query->where('id', '!=', $this->excludeEmployeeId);
        }

        return ! $query->exists();
    }

    public function message(): string
    {
        return "The {$this->column} is already registered to another active employee.";
    }
}
