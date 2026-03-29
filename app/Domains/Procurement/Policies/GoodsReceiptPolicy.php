<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Policies;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * GoodsReceiptPolicy.
 *
 * Permissions:
 *   procurement.goods-receipt.view
 *   procurement.goods-receipt.create
 *   procurement.goods-receipt.confirm
 */
final class GoodsReceiptPolicy
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
        return $user->hasPermissionTo('procurement.goods-receipt.view');
    }

    public function view(User $user, GoodsReceipt $gr): bool
    {
        return $user->hasPermissionTo('procurement.goods-receipt.view');
    }

    public function create(User $user): bool
    {
        // Both vendor users (via vendor portal markDelivered) and internal warehouse
        // staff with the permission can create GRs. Vendor scope is enforced at the
        // controller/service layer for vendor users.
        return $user->hasPermissionTo('procurement.goods-receipt.create');
    }

    public function submitForQc(User $user, GoodsReceipt $gr): bool
    {
        return $user->hasPermissionTo('procurement.goods-receipt.confirm')
            && $gr->status === 'draft';
    }

    public function confirm(User $user, GoodsReceipt $gr): bool
    {
        return $user->hasPermissionTo('procurement.goods-receipt.confirm')
            && in_array($gr->status, ['qc_passed', 'partial_accept'], true);
    }

    public function reject(User $user, GoodsReceipt $gr): bool
    {
        return $user->hasPermissionTo('procurement.goods-receipt.confirm')
            && in_array($gr->status, ['draft', 'pending_qc', 'qc_failed'], true);
    }

    public function acceptWithDefects(User $user, GoodsReceipt $gr): bool
    {
        return $user->hasPermissionTo('procurement.goods-receipt.confirm')
            && $gr->status === 'qc_failed';
    }

    public function resubmitForQc(User $user, GoodsReceipt $gr): bool
    {
        return $user->hasPermissionTo('procurement.goods-receipt.confirm')
            && $gr->status === 'qc_failed';
    }

    public function returnToSupplier(User $user, GoodsReceipt $gr): bool
    {
        return $user->hasPermissionTo('procurement.goods-receipt.confirm')
            && $gr->status === 'confirmed';
    }

    public function delete(User $user, GoodsReceipt $gr): bool
    {
        // Only vendor users can delete draft GRs they created
        return $user->hasPermissionTo('procurement.goods-receipt.create')
            && $user->vendor_id !== null
            && $gr->status === 'draft';
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
