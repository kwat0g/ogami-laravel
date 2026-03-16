<?php

declare(strict_types=1);

namespace App\Infrastructure\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Eloquent global scope that restricts queries to the authenticated user's
 * department(s) when the DepartmentScopeMiddleware is active.
 *
 * Apply to a model via the HasDepartmentScope trait.
 *
 * Multi-department support: reads `dept_scope.department_ids` (array) from
 * the service container, falling back to the legacy `dept_scope.department_id`
 * scalar alias. When more than one department ID is present, a `whereIn` is
 * used so that managers with cross-department access see records from ALL of
 * their assigned departments — not just the first one.
 */
class DepartmentScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $active = app()->bound('dept_scope.active') && app('dept_scope.active');

        if (! $active) {
            return;
        }

        $departmentIds = $this->resolveDepartmentIds();

        if (empty($departmentIds)) {
            return;
        }

        $table = $model->getTable();

        if (! $this->hasColumn($builder, 'department_id')) {
            return;
        }

        if (count($departmentIds) === 1) {
            $builder->where("{$table}.department_id", '=', $departmentIds[0]);
        } else {
            $builder->whereIn("{$table}.department_id", $departmentIds);
        }
    }

    /**
     * Resolve the list of department IDs the current user may see.
     *
     * Prefers the multi-value `dept_scope.department_ids` array bound by
     * DepartmentScopeMiddleware. Falls back to the legacy scalar
     * `dept_scope.department_id` for backward compatibility with any code
     * that still binds only the single value.
     *
     * @return list<int>
     */
    private function resolveDepartmentIds(): array
    {
        // Prefer the canonical multi-department array (set by DepartmentScopeMiddleware).
        if (app()->bound('dept_scope.department_ids')) {
            $ids = app('dept_scope.department_ids');

            if (is_array($ids) && ! empty($ids)) {
                return array_values(array_filter(array_map('intval', $ids)));
            }
        }

        // Legacy fallback: single scalar dept ID.
        if (app()->bound('dept_scope.department_id')) {
            $id = app('dept_scope.department_id');

            if ($id !== null) {
                return [(int) $id];
            }
        }

        return [];
    }

    /**
     * Check whether the model behind this builder actually has a
     * `department_id` column before we blindly append a WHERE clause.
     *
     * We check both the fillable list (declared columns) and the loaded
     * attributes (for models retrieved via raw queries or `select()`).
     */
    private function hasColumn(Builder $builder, string $column): bool
    {
        $model = $builder->getModel();

        return in_array($column, $model->getFillable(), true)
            || array_key_exists($column, $model->getAttributes());
    }
}
