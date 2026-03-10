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
}
