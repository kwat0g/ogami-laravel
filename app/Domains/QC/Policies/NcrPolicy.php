<?php

declare(strict_types=1);

namespace App\Domains\QC\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class NcrPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('qc.ncr.view');
    }

    public function view(User $user): bool
    {
        return $user->can('qc.ncr.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qc.ncr.create');
    }

    public function issueCapa(User $user): bool
    {
        return $user->can('qc.ncr.create');
    }

    public function close(User $user): bool
    {
        return $user->can('qc.ncr.close');
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
