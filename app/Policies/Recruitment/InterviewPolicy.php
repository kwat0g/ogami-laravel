<?php

declare(strict_types=1);

namespace App\Policies\Recruitment;

use App\Domains\HR\Recruitment\Models\InterviewSchedule;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class InterviewPolicy
{
    use HandlesAuthorization;

    private function inHrDepartment(User $user): bool
    {
        return $user->departments()->where('departments.code', 'HR')->exists()
            || $user->primaryDepartment?->code === 'HR'
            || $user->employee?->department?->code === 'HR';
    }

    private function hasHrRole(User $user, string $role): bool
    {
        return $user->hasRole($role)
            && $this->inHrDepartment($user);
    }

    private function isHrManager(User $user): bool
    {
        return $this->hasHrRole($user, 'manager');
    }

    private function isHrOfficer(User $user): bool
    {
        return $this->hasHrRole($user, 'officer');
    }

    private function isEligibleHrInterviewer(User $user): bool
    {
        return $this->hasHrRole($user, 'manager')
            || $this->hasHrRole($user, 'officer')
            || $this->hasHrRole($user, 'head');
    }

    private function canAccessRecruitment(User $user): bool
    {
        return $this->isEligibleHrInterviewer($user);
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
            || $user->hasPermissionTo('recruitment.interviews.view');
    }

    public function view(User $user, InterviewSchedule $interview): bool
    {
        // Interviewers can view their own assigned interviews
        if ($interview->interviewer_id === $user->id) {
            return true;
        }

        if ($interview->interviewer_department_id !== null
            && $user->departments()->where('departments.id', $interview->interviewer_department_id)->exists()) {
            return true;
        }

        return $this->canAccessRecruitment($user)
            || $user->hasPermissionTo('recruitment.interviews.view');
    }

    public function create(User $user): bool
    {
        return $this->isHrManager($user)
            || $this->isHrOfficer($user);
    }

    public function update(User $user, InterviewSchedule $interview): bool
    {
        return $this->isHrManager($user)
            || $this->isHrOfficer($user);
    }

    public function evaluate(User $user, InterviewSchedule $interview): bool
    {
        // Assigned HR interviewer can submit recommendation
        if ($interview->interviewer_id === $user->id) {
            return $this->isEligibleHrInterviewer($user);
        }

        if ($interview->interviewer_department_id !== null
            && $user->departments()->where('departments.id', $interview->interviewer_department_id)->exists()) {
            return $this->isEligibleHrInterviewer($user);
        }

        if ($user->hasPermissionTo('hr.full_access')) {
            return $this->isEligibleHrInterviewer($user);
        }

        return $this->isEligibleHrInterviewer($user)
            && $user->hasPermissionTo('recruitment.interviews.evaluate');
    }
}
