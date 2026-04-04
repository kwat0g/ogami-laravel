<?php

declare(strict_types=1);

namespace App\Policies\Recruitment;

use App\Domains\HR\Recruitment\Models\PreEmploymentChecklist;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PreEmploymentPolicy
{
    use HandlesAuthorization;

    private function isInHrDepartment(User $user): bool
    {
        return $user->departments()->where('departments.code', 'HR')->exists()
            || $user->primaryDepartment?->code === 'HR'
            || $user->employee?->department?->code === 'HR';
    }

    private function hasHrOperationalAccess(User $user): bool
    {
        return $user->hasPermissionTo('hr.full_access')
            || $user->hasPermissionTo('recruitment.hiring.execute')
            || (($user->hasRole('manager') || $user->hasRole('officer')) && $this->isInHrDepartment($user));
    }

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.preemployment.view')
            || $this->hasHrOperationalAccess($user);
    }

    public function submitDocument(User $user): bool
    {
        return $this->view($user);
    }

    public function verify(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.preemployment.verify')
            || $this->hasHrOperationalAccess($user);
    }

    public function complete(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.preemployment.verify');
    }
}
