<?php

declare(strict_types=1);

namespace App\Policies\Recruitment;

use App\Domains\HR\Recruitment\Models\PreEmploymentChecklist;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PreEmploymentPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.preemployment.view');
    }

    public function submitDocument(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.preemployment.view');
    }

    public function verify(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.preemployment.verify');
    }

    public function complete(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.preemployment.verify');
    }
}
