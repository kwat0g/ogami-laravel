<?php

declare(strict_types=1);

namespace App\Policies\Recruitment;

use App\Domains\HR\Recruitment\Models\InterviewSchedule;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class InterviewPolicy
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
        return $user->hasPermissionTo('recruitment.interviews.view');
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

        return $user->hasPermissionTo('recruitment.interviews.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.interviews.schedule');
    }

    public function update(User $user, InterviewSchedule $interview): bool
    {
        return $user->hasPermissionTo('recruitment.interviews.schedule');
    }

    public function evaluate(User $user, InterviewSchedule $interview): bool
    {
        // Only the assigned interviewer or HR can submit evaluations
        if ($interview->interviewer_id === $user->id) {
            return true;
        }

        if ($interview->interviewer_department_id !== null
            && $user->departments()->where('departments.id', $interview->interviewer_department_id)->exists()) {
            return true;
        }

        return $user->hasPermissionTo('recruitment.interviews.evaluate');
    }
}
