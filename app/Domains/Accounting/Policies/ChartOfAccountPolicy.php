<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Chart of Accounts Policy — matrix § General Ledger
 *
 * View: Finance Manager, Finance Supervisor, Executive (read-only)
 * Manage (create/edit/deactivate): Finance Manager only
 * COA rules enforced in ChartOfAccountService (not here).
 */
final class ChartOfAccountPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['chart_of_accounts.view', 'chart_of_accounts.manage']);
    }

    public function view(User $user, ChartOfAccount $account): bool
    {
        return $user->hasAnyPermission(['chart_of_accounts.view', 'chart_of_accounts.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('chart_of_accounts.manage');
    }

    public function update(User $user, ChartOfAccount $account): bool
    {
        return $user->hasPermissionTo('chart_of_accounts.manage');
    }

    public function delete(User $user, ChartOfAccount $account): bool
    {
        return $user->hasPermissionTo('chart_of_accounts.manage')
            && ! $account->is_system_account;
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
