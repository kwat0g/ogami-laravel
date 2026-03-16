<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\HR\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * DepartmentFactory — provides sensible test defaults for HR departments.
 *
 * @extends Factory<Department>
 */
final class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        $deptTypes = ['HR', 'Finance', 'Operations', 'Production', 'Sales', 'IT', 'Logistics', 'QC'];
        $deptType = $deptTypes[array_rand($deptTypes)];

        return [
            'code' => strtoupper(substr($deptType, 0, 3)).'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            'name' => $deptType.' Department '.$seq,
            'parent_department_id' => null,
            'plant_id' => null,
            'cost_center_code' => 'CC-'.strtoupper(substr($deptType, 0, 3)).'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            'annual_budget_centavos' => 0,
            'fiscal_year_start_month' => 1,
            'is_active' => true,
            'permission_profile_role' => null,
            'custom_permissions' => null,
        ];
    }

    /**
     * Mark department as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a parent department.
     */
    public function withParent(int $parentDepartmentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_department_id' => $parentDepartmentId,
        ]);
    }

    /**
     * Set department budget.
     */
    public function withBudget(int $budgetCentavos): static
    {
        return $this->state(fn (array $attributes) => [
            'annual_budget_centavos' => $budgetCentavos,
        ]);
    }
}
