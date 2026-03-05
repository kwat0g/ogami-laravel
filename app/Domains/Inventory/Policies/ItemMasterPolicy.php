<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\ItemMaster;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ItemMasterPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('inventory.items.view');
    }

    public function view(User $user, ItemMaster $item): bool
    {
        return $user->can('inventory.items.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.items.create');
    }

    public function update(User $user, ItemMaster $item): bool
    {
        return $user->can('inventory.items.edit');
    }

    public function adjust(User $user): bool
    {
        return $user->can('inventory.adjustments.create');
    }
}
