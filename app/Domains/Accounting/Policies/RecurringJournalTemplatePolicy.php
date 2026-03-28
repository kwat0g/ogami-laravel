<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\RecurringJournalTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class RecurringJournalTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.view');
    }

    public function view(User $user, RecurringJournalTemplate $template): bool
    {
        return $user->hasPermissionTo('accounting.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.journal_entry.create');
    }

    public function update(User $user, RecurringJournalTemplate $template): bool
    {
        return $user->hasPermissionTo('accounting.journal_entry.create');
    }

    public function delete(User $user, RecurringJournalTemplate $template): bool
    {
        return $user->hasPermissionTo('accounting.journal_entry.create');
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
