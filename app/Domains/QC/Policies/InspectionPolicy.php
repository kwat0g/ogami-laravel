<?php

declare(strict_types=1);

namespace App\Domains\QC\Policies;

use App\Domains\QC\Models\Inspection;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class InspectionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('qc.inspections.view');
    }

    public function view(User $user): bool
    {
        return $user->can('qc.inspections.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qc.inspections.create');
    }

    public function recordResults(User $user): bool
    {
        return $user->can('qc.inspections.create');
    }

    public function cancelResults(User $user, Inspection $inspection): bool
    {
        return $user->can('qc.inspections.create') && $inspection->status !== 'open';
    }

    public function delete(User $user, Inspection $inspection): bool
    {
        return $user->can('qc.inspections.create') && $inspection->status === 'open';
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
