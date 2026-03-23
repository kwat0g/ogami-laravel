<?php

declare(strict_types=1);

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\DeliverySchedule;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class DeliverySchedulePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(['admin', 'super_admin'])) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('production.delivery-schedule.view');
    }

    public function view(User $user, DeliverySchedule $deliverySchedule): bool
    {
        return $user->hasPermissionTo('production.delivery-schedule.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('production.delivery-schedule.manage');
    }

    public function update(User $user, DeliverySchedule $deliverySchedule): bool
    {
        return $user->hasPermissionTo('production.delivery-schedule.manage');
    }
}
