<?php

declare(strict_types=1);

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\DeliverySchedule;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class DeliverySchedulePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('production.delivery-schedule.view');
    }

    public function view(User $user, DeliverySchedule $ds): bool
    {
        return $user->hasPermissionTo('production.delivery-schedule.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('production.delivery-schedule.manage');
    }

    public function update(User $user, DeliverySchedule $ds): bool
    {
        return $user->hasPermissionTo('production.delivery-schedule.manage')
            && $ds->status !== 'delivered'
            && $ds->status !== 'cancelled';
    }
}
