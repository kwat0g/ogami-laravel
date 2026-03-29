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
        return $user->hasPermissionTo('recruitment.applications.review');
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
