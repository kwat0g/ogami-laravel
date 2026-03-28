<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\Production\Models\CombinedDeliverySchedule;
use App\Models\User;

class CombinedDeliverySchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('production.delivery-schedule.view')
            || $user->hasRole(['super_admin', 'admin']);
    }

    public function view(User $user, CombinedDeliverySchedule $schedule): bool
    {
        // Staff with permission can view all
        if ($user->hasPermissionTo('production.delivery-schedule.view')
            || $user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // Client can only view their own schedules
        if ($user->hasRole('client') && $user->client_id) {
            return $user->client_id === $schedule->customer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('production.delivery-schedule.manage')
            || $user->hasRole(['super_admin', 'admin']);
    }

    public function update(User $user, CombinedDeliverySchedule $schedule): bool
    {
        return $user->hasPermissionTo('production.delivery-schedule.manage')
            || $user->hasRole(['super_admin', 'admin']);
    }

    public function delete(User $user, CombinedDeliverySchedule $schedule): bool
    {
        return $user->hasPermissionTo('production.delivery-schedule.manage')
            || $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Client can acknowledge receipt of delivery
     */
    public function respond(User $user, CombinedDeliverySchedule $schedule): bool
    {
        return $user->hasRole('client')
            && $user->client_id
            && $user->client_id === $schedule->customer_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(\App\Models\User $user, $model): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(\App\Models\User $user, $model): bool
    {
        return $user->hasRole('super_admin');
    }
}
