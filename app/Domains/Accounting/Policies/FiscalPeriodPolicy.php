<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Fiscal Period Policy — matrix § General Ledger
 *
 * View: Finance Manager, Finance Supervisor, Executive
 * Open/close: Finance Manager only
 * Re-open closed period: Admin only (system.reopen_fiscal_period)
 * Re-open locked period: Nobody — absolute DB restriction
 */
final class FiscalPeriodPolicy
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
        return $user->hasAnyPermission(['fiscal_periods.view', 'fiscal_periods.manage']);
    }

    public function view(User $user, FiscalPeriod $period): bool
    {
        return $user->hasAnyPermission(['fiscal_periods.view', 'fiscal_periods.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('fiscal_periods.manage');
    }

    public function update(User $user, FiscalPeriod $period): bool
    {
        if ($period->status === 'locked') {
            return false; // Nobody can update a locked period
        }

        return $user->hasPermissionTo('fiscal_periods.manage');
    }

    public function open(User $user, FiscalPeriod $period): bool
    {
        if ($period->status === 'locked') {
            return false;
        }

        return $user->hasPermissionTo('fiscal_periods.manage');
    }

    public function close(User $user, FiscalPeriod $period): bool
    {
        return $user->hasPermissionTo('fiscal_periods.manage');
    }
}
