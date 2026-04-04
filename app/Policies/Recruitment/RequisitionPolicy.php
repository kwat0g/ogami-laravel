<?php

declare(strict_types=1);

namespace App\Policies\Recruitment;

use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class RequisitionPolicy
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
        return $user->hasPermissionTo('recruitment.requisitions.view');
    }

    public function view(User $user, JobRequisition $requisition): bool
    {
        return $user->hasPermissionTo('recruitment.requisitions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.requisitions.create');
    }

    public function update(User $user, JobRequisition $requisition): bool
    {
        return $user->hasPermissionTo('recruitment.requisitions.edit');
    }

    public function submit(User $user, JobRequisition $requisition): bool
    {
        return $user->hasPermissionTo('recruitment.requisitions.submit');
    }

    public function approve(User $user, JobRequisition $requisition): bool
    {
        if (! $user->hasPermissionTo('recruitment.requisitions.approve')) {
            return false;
        }

        // SoD: cannot approve own requisition
        return $user->id !== $requisition->requested_by;
    }

    public function reject(User $user, JobRequisition $requisition): bool
    {
        if (! $user->hasPermissionTo('recruitment.requisitions.reject')) {
            return false;
        }

        return $user->id !== $requisition->requested_by;
    }

    public function cancel(User $user, JobRequisition $requisition): bool
    {
        return $user->hasPermissionTo('recruitment.requisitions.cancel');
    }

    public function open(User $user, JobRequisition $requisition): bool
    {
        return $user->hasPermissionTo('recruitment.requisitions.approve');
    }
}
