<?php

declare(strict_types=1);

namespace App\Domains\AR\Policies;

use App\Domains\AR\Models\CustomerCreditNote;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy for AR customer credit/debit notes.
 * Reuses AR invoice permissions since credit notes are part of the AR workflow.
 */
final class CustomerCreditNotePolicy
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
        return $user->hasPermissionTo('customer_invoices.view');
    }

    public function view(User $user, CustomerCreditNote $note): bool
    {
        return $user->hasPermissionTo('customer_invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('customer_invoices.create');
    }

    /** Only draft notes may be updated. */
    public function update(User $user, CustomerCreditNote $note): bool
    {
        if ($note->status !== 'draft') {
            return false;
        }

        return $user->hasPermissionTo('customer_invoices.update');
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
