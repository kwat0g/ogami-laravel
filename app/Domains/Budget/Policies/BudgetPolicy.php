<?php

declare(strict_types=1);

namespace App\Domains\Budget\Policies;

use App\Domains\Budget\Models\AnnualBudget;
use App\Domains\Budget\Models\CostCenter;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class BudgetPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('budget.view');
    }

    public function view(User $user, CostCenter|AnnualBudget $model): bool
    {
        return $user->hasPermissionTo('budget.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('budget.manage');
    }

    public function update(User $user, CostCenter|AnnualBudget $model): bool
    {
        return $user->hasPermissionTo('budget.manage');
    }

    public function delete(User $user, CostCenter|AnnualBudget $model): bool
    {
        return $user->hasPermissionTo('budget.manage');
    }

    /** Approve an annual budget — requires budget.approve or budget.manage permission. */
    public function approve(User $user): bool
    {
        return $user->hasAnyPermission(['budget.approve', 'budget.manage']);
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
