<?php

declare(strict_types=1);

namespace App\Domains\CRM\Policies;

use App\Domains\CRM\Models\Ticket;
use App\Models\User;

final class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['crm.tickets.view', 'crm.tickets.manage']);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        // Clients can only view their own tickets
        if ($user->hasRole('client')) {
            return (int) $ticket->client_user_id === $user->id;
        }

        return $user->hasAnyPermission(['crm.tickets.view', 'crm.tickets.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['crm.tickets.create', 'crm.tickets.manage']);
    }

    public function reply(User $user, Ticket $ticket): bool
    {
        if ($ticket->status === 'closed') {
            return false;
        }

        // Clients can only reply to their own tickets
        if ($user->hasRole('client')) {
            return (int) $ticket->client_user_id === $user->id;
        }

        return $user->hasAnyPermission(['crm.tickets.reply', 'crm.tickets.manage']);
    }

    public function assign(User $user): bool
    {
        return $user->hasPermissionTo('crm.tickets.assign');
    }

    public function resolve(User $user): bool
    {
        return $user->hasAnyPermission(['crm.tickets.manage']);
    }

    public function close(User $user): bool
    {
        return $user->hasPermissionTo('crm.tickets.close');
    }

    public function reopen(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole('client')) {
            return (int) $ticket->client_user_id === $user->id;
        }

        return $user->hasAnyPermission(['crm.tickets.manage']);
    }
}
