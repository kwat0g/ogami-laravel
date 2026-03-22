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

    public function confirm(User $user, GoodsReceipt $gr): bool
    {
        return $user->hasPermissionTo('procurement.goods-receipt.confirm')
            && $gr->status === 'draft';
    }

    public function delete(User $user, GoodsReceipt $gr): bool
    {
        // Only vendor users can delete draft GRs they created
        return $user->hasPermissionTo('procurement.goods-receipt.create')
            && $user->vendor_id !== null
            && $gr->status === 'draft';
    }
}
