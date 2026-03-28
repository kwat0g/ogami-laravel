<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Policies;

use App\Domains\Procurement\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * PurchaseOrderPolicy.
 *
 * Permissions:
 *   procurement.purchase-order.view
 *   procurement.purchase-order.create
 *   procurement.purchase-order.manage
 */
final class PurchaseOrderPolicy
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
        return $user->hasPermissionTo('procurement.purchase-order.view');
    }

    public function view(User $user, PurchaseOrder $po): bool
    {
        return $user->hasPermissionTo('procurement.purchase-order.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('procurement.purchase-order.create');
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        return $user->hasPermissionTo('procurement.purchase-order.manage')
            && $po->status === 'draft';
    }

    public function send(User $user, PurchaseOrder $po): bool
    {
        return $user->hasPermissionTo('procurement.purchase-order.manage')
            && $po->status === 'draft';
    }

    public function cancel(User $user, PurchaseOrder $po): bool
    {
        return $user->hasPermissionTo('procurement.purchase-order.manage')
            && $po->status === 'draft';
    }

    /**
     * Manage negotiation actions: accept/reject vendor changes, assign vendor.
     * Requires manage permission — available to purchasing officers and managers.
     */
    public function manage(User $user, PurchaseOrder $po): bool
    {
        return $user->hasPermissionTo('procurement.purchase-order.manage');
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
