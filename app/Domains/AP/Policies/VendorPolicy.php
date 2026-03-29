<?php

declare(strict_types=1);

namespace App\Domains\AP\Policies;

use App\Domains\AP\Models\Vendor;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Vendor Policy.
 *
 * Permissions (to be added to RolePermissionSeeder):
 *   vendors.view, vendors.create, vendors.update, vendors.archive
 */
final class VendorPolicy
{
    use HandlesAuthorization;

    /** Admin bypass — admin role has unconditional access to all resources. */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('vendors.view');
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('vendors.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendors.manage');
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('vendors.manage');
    }

    /** Archive (soft-delete) is a manager-level action. */
    public function archive(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('vendors.archive');
    }

    /** Accredit a vendor — Purchasing Officer or above. */
    public function accredit(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('vendors.accredit');
    }

    /** Suspend a vendor — Purchasing Officer or above. */
    public function suspend(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('vendors.suspend');
    }

    /** Manage vendor items — Purchasing Officer or above (used by VendorItemController). */
    public function manage(User $user, Vendor $vendor): bool
    {
        return $user->hasAnyPermission(['vendors.update', 'vendors.create', 'vendors.manage']);
    }

    /** Provision a vendor portal user account — admin / system user management only. */
    public function provisionAccount(User $user, Vendor $vendor): bool
    {
        return $user->hasPermissionTo('system.manage_users');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(\App\Models\User $user, $model): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(\App\Models\User $user, $model): bool
    {
        return $user->hasRole('super_admin');
    }
}
