<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class DeliveryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('delivery.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('delivery.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('delivery.manage');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('delivery.manage');
    }

    public function confirm(User $user): bool
    {
        return $user->hasPermissionTo('delivery.manage');
    }
}
