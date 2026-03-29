<?php

declare(strict_types=1);

namespace App\Policies\Recruitment;

use App\Domains\HR\Recruitment\Models\Hiring;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class HiringPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function execute(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.hiring.execute');
    }
}
