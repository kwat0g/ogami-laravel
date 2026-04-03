<?php

declare(strict_types=1);

namespace App\Policies\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ApplicationPolicy
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
        return $user->hasPermissionTo('recruitment.applications.view');
    }

    public function view(User $user, Application $application): bool
    {
        return $user->hasPermissionTo('recruitment.applications.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('manager')
            && $user->hasPermissionTo('recruitment.applications.create');
    }

    public function delete(User $user, Application $application): bool
    {
        if (! $user->hasRole('manager') || ! $user->hasPermissionTo('recruitment.applications.delete')) {
            return false;
        }

        return in_array($application->status->value, ['new', 'under_review'], true);
    }

    public function review(User $user, Application $application): bool
    {
        return $user->hasPermissionTo('recruitment.applications.review');
    }

    public function shortlist(User $user, Application $application): bool
    {
        return $user->hasPermissionTo('recruitment.applications.shortlist');
    }

    public function reject(User $user, Application $application): bool
    {
        return $user->hasPermissionTo('recruitment.applications.reject');
    }
}
