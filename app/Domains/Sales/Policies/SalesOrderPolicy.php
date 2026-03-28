<?php

declare(strict_types=1);

namespace App\Domains\Sales\Policies;

use App\Domains\Sales\Models\SalesOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * SalesOrderPolicy.
 *
 * Permissions:
 *   sales.orders.view
 *   sales.orders.manage
 *   sales.orders.confirm   (SoD: creator !== confirmer)
 *   sales.orders.cancel
 */
final class SalesOrderPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sales.orders.view');
    }

    public function view(User $user, SalesOrder $order): bool
    {
        return $user->hasPermissionTo('sales.orders.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.orders.manage');
    }

    /**
     * SoD: The user who created the order cannot confirm it.
     */
    public function confirm(User $user, SalesOrder $order): bool
    {
        return $user->hasPermissionTo('sales.orders.confirm')
            && $user->id !== $order->created_by_id
            && $order->status === 'draft';
    }

    public function cancel(User $user, SalesOrder $order): bool
    {
        return $user->hasPermissionTo('sales.orders.cancel')
            && in_array($order->status, ['draft', 'confirmed'], true);
    }
}
