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
}
