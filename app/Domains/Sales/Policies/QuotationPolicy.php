<?php

declare(strict_types=1);

namespace App\Domains\Sales\Policies;

use App\Domains\Sales\Models\Quotation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * QuotationPolicy.
 *
 * Permissions:
 *   sales.quotations.view
 *   sales.quotations.create
 *   sales.quotations.update
 *   sales.quotations.send
 *   sales.quotations.accept
 *   sales.quotations.manage
 *   sales.orders.confirm   (for convert-to-order)
 */
final class QuotationPolicy
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
        return $user->hasPermissionTo('sales.quotations.view');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('sales.quotations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.quotations.create');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('sales.quotations.update')
            && $quotation->status === 'draft';
    }

    public function send(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('sales.quotations.send')
            && $quotation->status === 'draft';
    }

    public function accept(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('sales.quotations.accept')
            && $quotation->status === 'sent';
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('sales.quotations.manage')
            && $quotation->status === 'sent';
    }

    public function convertToOrder(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('sales.orders.confirm')
            && $quotation->status === 'accepted';
    }
}
