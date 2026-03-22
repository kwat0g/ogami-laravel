<?php

declare(strict_types=1);

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ProductionOrderPolicy
{
    use HandlesAuthorization;

    /** Admin/SuperAdmin bypass */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('production.orders.view');
    }

    public function view(User $user, ProductionOrder $order): bool
    {
        return $user->hasPermissionTo('production.orders.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('production.orders.create');
    }

    public function update(User $user, ProductionOrder $order): bool
    {
        return $user->hasPermissionTo('production.orders.create')
            && $order->status === 'draft';
    }

    public function release(User $user, ProductionOrder $order): bool
    {
        return $user->hasPermissionTo('production.orders.release')
            && $order->status === 'draft';
    }

    public function start(User $user, ProductionOrder $order): bool
    {
        return $user->hasPermissionTo('production.orders.release')
            && $order->status === 'released';
    }

    public function complete(User $user, ProductionOrder $order): bool
    {
        return $user->hasPermissionTo('production.orders.complete')
            && $order->status === 'in_progress';
    }

    public function cancel(User $user, ProductionOrder $order): bool
    {
        return $user->hasPermissionTo('production.orders.create')
            && in_array($order->status, ['draft', 'released'], true);
    }

    public function logOutput(User $user, ProductionOrder $order): bool
    {
        return $user->hasPermissionTo('production.orders.log_output')
            && in_array($order->status, ['released', 'in_progress'], true);
    }
}
