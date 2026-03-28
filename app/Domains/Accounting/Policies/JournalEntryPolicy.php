<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\JournalEntry;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Journal Entry Policy.
 *
 * JE-006: posted JEs cannot be updated or deleted — policy returns false.
 * JE-008: auto-posted JEs (source_type != 'manual') cannot be manually edited.
 * SoD (JE-010): the poster cannot be the drafter — checked in JournalEntryService.
 *
 * Permission names match RolePermissionSeeder:
 *   journal_entries.view, .create, .update, .submit, .post, .reverse, .export
 */
final class JournalEntryPolicy
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
        return $user->hasPermissionTo('journal_entries.view');
    }

    public function view(User $user, JournalEntry $je): bool
    {
        return $user->hasPermissionTo('journal_entries.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('journal_entries.create');
    }

    /**
     * JE-006: posted JEs are immutable.
     * JE-008: auto-posted (system) JEs cannot be manually edited.
     */
    public function update(User $user, JournalEntry $je): bool
    {
        if ($je->isPosted()) {
            return false; // JE-006
        }

        if ($je->isAutoPosted()) {
            return false; // JE-008
        }

        return $user->hasPermissionTo('journal_entries.update');
    }

    public function submit(User $user, JournalEntry $je): bool
    {
        if ($je->isPosted() || $je->isAutoPosted()) {
            return false;
        }

        return $user->hasPermissionTo('journal_entries.submit');
    }

    public function post(User $user, JournalEntry $je): bool
    {
        return $user->hasPermissionTo('journal_entries.post');
    }

    public function reverse(User $user, JournalEntry $je): bool
    {
        if (! $je->isPosted()) {
            return false; // can only reverse posted JEs
        }

        return $user->hasPermissionTo('journal_entries.reverse');
    }

    /**
     * JE-006: posted JEs cannot be deleted.
     * Draft/submitted/stale JEs can be cancelled (not hard-deleted at this time).
     * Requires journal_entries.create (i.e., user can manage JEs in their dept).
     */
    public function delete(User $user, JournalEntry $je): bool
    {
        if ($je->isPosted()) {
            return false; // JE-006
        }

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
