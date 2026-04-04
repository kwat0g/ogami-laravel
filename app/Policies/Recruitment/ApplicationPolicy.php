<?php

declare(strict_types=1);

namespace App\Policies\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ApplicationPolicy
{
    use HandlesAuthorization;

    private function inHrDepartment(User $user): bool
    {
        return $user->departments()->where('departments.code', 'HR')->exists()
            || $user->primaryDepartment?->code === 'HR'
            || $user->employee?->department?->code === 'HR';
    }

    private function isHrManager(User $user): bool
    {
        return $user->hasRole('manager')
            && $this->inHrDepartment($user);
    }

    private function isHrOfficer(User $user): bool
    {
        return $user->hasRole('officer')
            && $this->inHrDepartment($user);
    }

    private function isHrHead(User $user): bool
    {
        return $user->hasRole('head')
            && $this->inHrDepartment($user);
    }

    private function canAccessRecruitment(User $user): bool
    {
        return $this->isHrManager($user)
            || $this->isHrOfficer($user)
            || $this->isHrHead($user);
    }

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->canAccessRecruitment($user)
            || $user->hasPermissionTo('recruitment.applications.view');
    }

    public function view(User $user, Application $application): bool
    {
        return $this->canAccessRecruitment($user)
            || $user->hasPermissionTo('recruitment.applications.view');
    }

    public function create(User $user): bool
    {
        if ($user->hasPermissionTo('hr.full_access')) {
            return $this->isHrManager($user) || $this->isHrOfficer($user);
        }

        return ($this->isHrManager($user) || $this->isHrOfficer($user))
            && $user->hasPermissionTo('recruitment.applications.create');
    }

    public function delete(User $user, Application $application): bool
    {
        if (! $this->isHrManager($user) || ! $user->hasPermissionTo('recruitment.applications.delete')) {
            return false;
        }

        return in_array($application->status->value, ['new', 'under_review'], true);
    }

    public function review(User $user, Application $application): bool
    {
        if ($user->hasPermissionTo('hr.full_access')) {
            return $this->isHrManager($user) || $this->isHrOfficer($user);
        }

        return $this->isHrManager($user)
            && $user->hasPermissionTo('recruitment.applications.review');
    }

    public function shortlist(User $user, Application $application): bool
    {
        if ($user->hasPermissionTo('hr.full_access')) {
            return $this->isHrManager($user) || $this->isHrOfficer($user);
        }

        return $this->isHrManager($user)
            && $user->hasPermissionTo('recruitment.applications.shortlist');
    }

    public function reject(User $user, Application $application): bool
    {
        if ($user->hasPermissionTo('hr.full_access')) {
            return $this->isHrManager($user) || $this->isHrOfficer($user);
        }

        return $this->isHrManager($user)
            && $user->hasPermissionTo('recruitment.applications.reject');
    }
}
