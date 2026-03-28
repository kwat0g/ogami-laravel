<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\MaterialRequisition;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class MaterialRequisitionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('inventory.mrq.view');
    }

    public function view(User $user, MaterialRequisition $mrq): bool
    {
        return $user->can('inventory.mrq.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.mrq.create');
    }

    public function update(User $user, MaterialRequisition $mrq): bool
    {
        return $user->can('inventory.mrq.create') && $mrq->status === 'draft';
    }

    public function note(User $user, MaterialRequisition $mrq): bool
    {
        return $user->can('inventory.mrq.note')
            && $mrq->status === 'submitted'
            && $user->id !== $mrq->requested_by_id;
    }

    public function check(User $user, MaterialRequisition $mrq): bool
    {
        return $user->can('inventory.mrq.check')
            && $mrq->status === 'noted'
            && $user->id !== $mrq->noted_by_id;
    }

    public function review(User $user, MaterialRequisition $mrq): bool
    {
        return $user->can('inventory.mrq.review')
            && $mrq->status === 'checked'
            && $user->id !== $mrq->checked_by_id;
    }

    public function vpApprove(User $user, MaterialRequisition $mrq): bool
    {
        return $user->can('inventory.mrq.vp_approve')
            && $mrq->status === 'reviewed'
            && $user->id !== $mrq->reviewed_by_id;
    }

    public function reject(User $user, MaterialRequisition $mrq): bool
    {
        return $user->can('inventory.mrq.note')
            && ! in_array($mrq->status, ['draft', 'cancelled', 'fulfilled', 'rejected'], true);
    }

    public function cancel(User $user, MaterialRequisition $mrq): bool
    {
        return $mrq->isCancellable()
            && ($user->id === $mrq->requested_by_id || $user->can('inventory.mrq.view'));
    }

    public function fulfill(User $user, MaterialRequisition $mrq): bool
    {
        return $user->can('inventory.mrq.fulfill') && $mrq->status === 'approved';
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
