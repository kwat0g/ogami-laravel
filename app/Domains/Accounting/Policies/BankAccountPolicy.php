<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\BankAccount;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Bank Account Policy.
 *
 * Only managers and admins can create, update, or deactivate bank accounts.
 * Supervisors and above can view.
 *
 * Permission names: bank_accounts.view, .create, .update, .delete
 */
final class BankAccountPolicy
{
    use HandlesAuthorization;

    /** Admin bypass — admin role has unconditional access to all resources. */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('bank_accounts.view');
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $user->hasPermissionTo('bank_accounts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('bank_accounts.create');
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $user->hasPermissionTo('bank_accounts.update');
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $user->hasPermissionTo('bank_accounts.delete');
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
