<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\WarehouseLocation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * WarehouseLocationPolicy — controls access to warehouse location CRUD.
 *
 * Without this policy, all $this->authorize() calls in WarehouseLocationController
 * fail with 403 because Laravel denies by default when no policy is registered.
 */
final class WarehouseLocationPolicy
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
        return $user->hasAnyPermission(['inventory.view', 'inventory.locations.view']);
    }

    public function view(User $user, WarehouseLocation $location): bool
    {
        return $user->hasAnyPermission(['inventory.view', 'inventory.locations.view']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['inventory.manage', 'inventory.locations.manage']);
    }

    public function update(User $user, WarehouseLocation $location): bool
    {
        return $user->hasAnyPermission(['inventory.manage', 'inventory.locations.manage']);
    }

    public function delete(User $user, WarehouseLocation $location): bool
    {
        return $user->hasAnyPermission(['inventory.manage', 'inventory.locations.manage']);
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
