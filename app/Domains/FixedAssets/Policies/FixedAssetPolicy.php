<?php

declare(strict_types=1);

namespace App\Domains\FixedAssets\Policies;

use App\Domains\FixedAssets\Models\FixedAsset;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class FixedAssetPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('fixed_assets.view');
    }

    public function view(User $user, FixedAsset $asset): bool
    {
        return $user->hasPermissionTo('fixed_assets.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('fixed_assets.manage');
    }

    public function update(User $user, FixedAsset $asset): bool
    {
        return $user->hasPermissionTo('fixed_assets.manage');
    }

    public function dispose(User $user, FixedAsset $asset): bool
    {
        return $user->hasPermissionTo('fixed_assets.manage');
    }

    public function depreciate(User $user): bool
    {
        return $user->hasPermissionTo('fixed_assets.manage') && $user->hasRole(['admin', 'super_admin', 'executive', 'vice_president']);
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
