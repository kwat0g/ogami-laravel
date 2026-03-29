<?php

declare(strict_types=1);

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\BillOfMaterials;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * BOM Policy — controls access to Bill of Materials CRUD.
 *
 * Previously BillOfMaterials was incorrectly mapped to ProductionOrderPolicy,
 * which caused type mismatches when policy methods received a BOM model
 * instead of a ProductionOrder model.
 */
final class BomPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['production.bom.view', 'production.view']);
    }

    public function view(User $user, BillOfMaterials $bom): bool
    {
        return $user->hasAnyPermission(['production.bom.view', 'production.view']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['production.bom.manage', 'production.manage']);
    }

    public function update(User $user, BillOfMaterials $bom): bool
    {
        return $user->hasAnyPermission(['production.bom.manage', 'production.manage']);
    }

    public function delete(User $user, BillOfMaterials $bom): bool
    {
        return $user->hasAnyPermission(['production.bom.manage', 'production.manage']);
    }

    public function restore(User $user, $model): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDelete(User $user, $model): bool
    {
        return $user->hasRole('super_admin');
    }
}
