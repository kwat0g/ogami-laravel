<?php

declare(strict_types=1);

namespace App\Domains\Tax\Policies;

use App\Domains\Tax\Models\BirFiling;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * BIR Filing Policy — only Finance / Tax roles may view or manage BIR forms.
 */
final class BirFilingPolicy
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
        return $user->hasPermissionTo('reports.vat');
    }

    public function view(User $user, BirFiling $birFiling): bool
    {
        return $user->hasPermissionTo('reports.vat');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('fiscal_periods.manage');
    }

    public function update(User $user, BirFiling $birFiling): bool
    {
        return $user->hasPermissionTo('fiscal_periods.manage');
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
