<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Infrastructure\Scopes\DepartmentScope;

/**
 * Applies the DepartmentScope global scope to any Eloquent model.
 *
 * Usage:
 *   class Employee extends Model
 *   {
 *       use HasDepartmentScope;
 *   }
 *
 * Moved from App\Infrastructure\Traits to App\Shared\Traits (Sprint 2.6).
 */
trait HasDepartmentScope
{
    public static function bootHasDepartmentScope(): void
    {
        static::addGlobalScope(new DepartmentScope);
    }

    /** Convenience scope to remove the department filter for a single query. */
    public function scopeWithoutDepartmentScope($query)
    {
        return $query->withoutGlobalScope(DepartmentScope::class);
    }
}
