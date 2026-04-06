<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Policies;

use App\Domains\Inventory\Models\MaterialRequisition;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class MaterialRequisitionPolicy
{
    use HandlesAuthorization;

    private function isWarehouseManager(User $user): bool
    {
        return $user->hasRole('manager')
            && $user->departments()->where('departments.code', 'WH')->exists();
    }

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

    /**
     * Warehouse Manager review is the single approval gate.
     * SoD: approver must not be the submitter.
     */
    public function review(User $user, MaterialRequisition $mrq): bool
    {
        return $user->can('inventory.mrq.review')
            && $mrq->status === 'submitted'
            && (int) $user->id !== (int) $mrq->submitted_by_id;
    }

    public function reject(User $user, MaterialRequisition $mrq): bool
    {
        return $user->can('inventory.mrq.review')
            && $mrq->status === 'submitted'
            && (int) $user->id !== (int) $mrq->submitted_by_id;
    }

    public function cancel(User $user, MaterialRequisition $mrq): bool
    {
        return $mrq->isCancellable()
            && $user->id === $mrq->requested_by_id;
    }

    public function fulfill(User $user, MaterialRequisition $mrq): bool
    {
        return $user->can('inventory.mrq.fulfill')
            && in_array($mrq->status, ['approved', 'converted_to_pr'], true);
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
