<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Policies;

use App\Domains\Procurement\Models\VendorRfq;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * VendorRfqPolicy
 *
 * RFQ management is handled by purchasing_officer and managers.
 * Uses the existing procurement.purchase-order permissions as a proxy since
 * RFQ is part of the sourcing sub-process.
 */
final class VendorRfqPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('procurement.purchase-order.view');
    }

    public function view(User $user, VendorRfq $rfq): bool
    {
        return $user->hasPermissionTo('procurement.purchase-order.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('procurement.purchase-order.manage');
    }

    public function update(User $user, VendorRfq $rfq): bool
    {
        return $user->hasPermissionTo('procurement.purchase-order.manage');
    }

    public function delete(User $user, VendorRfq $rfq): bool
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
