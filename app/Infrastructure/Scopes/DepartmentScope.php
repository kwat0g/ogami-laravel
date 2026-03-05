<?php

declare(strict_types=1);

namespace App\Infrastructure\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Eloquent global scope that restricts queries to the authenticated user's
 * department when the DepartmentScopeMiddleware is active.
 *
 * Apply to a model via the HasDepartmentScope trait.
 */
class DepartmentScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $active = app()->bound('dept_scope.active') && app('dept_scope.active');
        $departmentId = app()->bound('dept_scope.department_id')
            ? app('dept_scope.department_id')
            : null;

        if ($active && $departmentId !== null) {
            $table = $model->getTable();

            if ($this->hasColumn($builder, 'department_id')) {
                $builder->where("{$table}.department_id", '=', $departmentId);
            }
        }
    }

    private function hasColumn(Builder $builder, string $column): bool
    {
        $model = $builder->getModel();

        return in_array($column, $model->getFillable(), true)
            || isset($model->getAttributes()[$column]);
    }
}
