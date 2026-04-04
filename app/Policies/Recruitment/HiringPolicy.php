<?php

declare(strict_types=1);

namespace App\Policies\Recruitment;

use App\Domains\HR\Recruitment\Models\Hiring;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class HiringPolicy
{
    use HandlesAuthorization;

    private function isInHrDepartment(User $user): bool
    {
        return $user->departments()->where('departments.code', 'HR')->exists()
            || $user->primaryDepartment?->code === 'HR'
            || $user->employee?->department?->code === 'HR';
    }

    private function isHrManager(User $user): bool
    {
        return $user->hasRole('manager')
            && $this->isInHrDepartment($user);
    }

    private function hasHrHiringAccess(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.hiring.execute')
            || $user->hasPermissionTo('hr.full_access')
            || $this->isHrManager($user)
            || (($user->hasRole('officer') || $user->hasRole('head')) && $this->isInHrDepartment($user));
    }

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function execute(User $user): bool
    {
        return $this->hasHrHiringAccess($user);
    }

    public function approve(User $user): bool
    {
        return $this->hasHrHiringAccess($user);
    }
}
