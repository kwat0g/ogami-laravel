<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Bank Reconciliation Policy.
 *
 * SoD: only a different user (not the drafter) can certify.
 * Certification is restricted to managers.
 *
 * Permission names: bank_reconciliations.view, .create, .certify
 */
final class BankReconciliationPolicy
{
    use HandlesAuthorization;

    /** Admin bypass — admin role has unconditional access to all resources. */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('bank_reconciliations.view');
    }

    public function view(User $user, BankReconciliation $reconciliation): bool
    {
        return $user->hasPermissionTo('bank_reconciliations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('bank_reconciliations.create');
    }

    /**
     * Certify — requires manager-level permission AND SoD check.
     * The SodViolationException is thrown in BankReconciliationService::certify()
     * rather than here, so the error code is consistent with the rest of the app.
     */
    public function certify(User $user, BankReconciliation $reconciliation): bool
    {
        if (! $user->hasPermissionTo('bank_reconciliations.certify')) {
            return false;
        }

        // Service-level SoD enforces the certifier != drafter check with proper error code.
        // The policy only checks the permission gate.
        return true;
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
