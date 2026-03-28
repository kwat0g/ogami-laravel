<?php

declare(strict_types=1);

namespace App\Domains\Tax\Policies;

use App\Domains\Tax\Models\VatLedger;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * VAT Ledger Policy — matrix § Accounts Receivable / GL
 *
 * View: Finance Manager only
 * Close period: Finance Manager (fiscal_periods.manage)
 */
final class VatLedgerPolicy
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
        return $user->hasPermissionTo('reports.vat');
    }

    public function view(User $user, VatLedger $vatLedger): bool
    {
        return $user->hasPermissionTo('reports.vat');
    }

    /** VAT-004: closing a period requires Finance Manager */
    public function closePeriod(User $user, VatLedger $vatLedger): bool
    {
        if ($vatLedger->is_closed) {
            return false;
        }

        return $user->hasPermissionTo('fiscal_periods.manage');
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
