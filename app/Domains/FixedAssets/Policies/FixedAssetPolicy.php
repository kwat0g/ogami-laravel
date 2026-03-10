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
}
