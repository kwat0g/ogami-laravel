<?php

declare(strict_types=1);

namespace App\Domains\CRM\Policies;

use App\Domains\CRM\Models\ClientOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ClientOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Allow admins to bypass all checks.
     * Returns null for everyone else (continue to specific method).
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sales.order_review');
    }

    public function view(User $user, ClientOrder $order): bool
    {
        // Client can view their own orders
        if ($user->hasRole('client') && $user->client_id === $order->customer_id) {
            return true;
        }

        return $user->hasPermissionTo('sales.order_review');
    }

    public function create(User $user): bool
    {
        // Client with linked account OR sales staff
        return ($user->hasRole('client') && $user->client_id !== null)
            || $user->hasPermissionTo('sales.order_review');
    }

    /**
     * SOD-CLIENT-001: Submitter cannot approve.
     */
    public function approve(User $user, ClientOrder $order): bool
    {
        if ($order->submitted_by === $user->id) {
            return false;
        }

        return $user->hasPermissionTo('sales.order_approve');
    }

    /**
     * SOD-CLIENT-001: Submitter cannot reject.
     */
    public function reject(User $user, ClientOrder $order): bool
    {
        if ($order->submitted_by === $user->id) {
            return false;
        }

        return $user->hasPermissionTo('sales.order_reject');
    }

    public function negotiate(User $user, ClientOrder $order): bool
    {
        return $user->hasPermissionTo('sales.order_negotiate');
    }

    public function salesRespond(User $user, ClientOrder $order): bool
    {
        return $user->hasPermissionTo('sales.order_negotiate');
    }

    /**
     * Client-side response: must own the order AND it must be their turn (negotiating).
     */
    public function respond(User $user, ClientOrder $order): bool
    {
        return $user->hasRole('client')
            && $user->client_id === $order->customer_id
            && $order->status === ClientOrder::STATUS_NEGOTIATING;
    }

    /**
     * Cancel: client can cancel their own pending/negotiating orders.
     * Sales staff with reject permission can cancel pre-approval orders.
     */
    public function cancel(User $user, ClientOrder $order): bool
    {
        if ($user->hasRole('client')) {
            return $user->client_id === $order->customer_id
                && in_array($order->status, [
                    ClientOrder::STATUS_PENDING,
                    ClientOrder::STATUS_NEGOTIATING,
                ], true);
        }

        return $user->hasPermissionTo('sales.order_reject');
    }

    /**
     * VP approval for high-value orders in vp_pending status.
     */
    public function vpApprove(User $user, ClientOrder $order): bool
    {
        return $user->hasPermissionTo('sales.order_vp_approve')
            || $user->hasRole(['vice_president', 'executive']);
    }
}
