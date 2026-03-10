<?php

declare(strict_types=1);

namespace App\Domains\AP\Policies;

use App\Domains\AP\Models\VendorCreditNote;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy for AP vendor credit/debit notes.
 * Reuses AP invoice permissions since credit notes are part of the AP workflow.
 */
final class VendorCreditNotePolicy
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
        return $user->hasPermissionTo('vendor_invoices.view');
    }

    public function view(User $user, VendorCreditNote $note): bool
    {
        return $user->hasPermissionTo('vendor_invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor_invoices.create');
    }

    /** Only draft notes may be updated. */
    public function update(User $user, VendorCreditNote $note): bool
    {
        if ($note->status !== 'draft') {
            return false;
        }

        return $user->hasPermissionTo('vendor_invoices.update');
    }
}
