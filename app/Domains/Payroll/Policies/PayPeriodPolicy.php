<?php

declare(strict_types=1);

namespace App\Domains\Payroll\Policies;

use App\Domains\Payroll\Models\PayPeriod;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PayPeriodPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('payroll.manage_pay_periods') || $user->can('payroll.initiate');
    }

    public function view(User $user, PayPeriod $payPeriod): bool
    {
        return $user->can('payroll.manage_pay_periods') || $user->can('payroll.initiate');
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.manage_pay_periods') || $user->can('payroll.initiate');
    }

    public function update(User $user, PayPeriod $payPeriod): bool
    {
        return $user->can('payroll.manage_pay_periods') || $user->can('payroll.initiate');
    }

    public function delete(User $user, PayPeriod $payPeriod): bool
    {
        return $user->can('payroll.manage_pay_periods');
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
