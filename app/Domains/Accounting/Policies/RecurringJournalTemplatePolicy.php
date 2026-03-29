<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\RecurringJournalTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class RecurringJournalTemplatePolicy
{
    use HandlesAuthorization;

    /** Admin bypass — admin role has unconditional access to all resources. */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['journal_entries.view', 'journal_entries.create']);
    }

    public function view(User $user, RecurringJournalTemplate $template): bool
    {
        return $user->hasAnyPermission(['journal_entries.view', 'journal_entries.create']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('journal_entries.create');
    }

    public function update(User $user, RecurringJournalTemplate $template): bool
    {
        return $user->hasPermissionTo('journal_entries.create');
    }

    public function delete(User $user, RecurringJournalTemplate $template): bool
    {
        return $user->hasPermissionTo('journal_entries.create');
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
