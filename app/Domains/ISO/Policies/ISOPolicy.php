<?php

declare(strict_types=1);

namespace App\Domains\ISO\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ISO Policy — controls access to Controlled Documents and Internal Audits.
 *
 * Without this policy, all $this->authorize() calls in ISOController
 * fail with 403 because Laravel denies by default when no policy is registered.
 *
 * Permissions:
 *   iso.documents.view    — view controlled documents
 *   iso.documents.manage  — create/update controlled documents
 *   iso.audits.view       — view internal audits
 *   iso.audits.manage     — create/complete/add findings to audits
 */
final class ISOPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'iso.documents.view',
            'iso.documents.manage',
            'iso.audits.view',
            'iso.audits.manage',
            'iso.view',
            'iso.manage',
        ]);
    }

    public function view(User $user, $model = null): bool
    {
        return $user->hasAnyPermission([
            'iso.documents.view',
            'iso.documents.manage',
            'iso.audits.view',
            'iso.audits.manage',
            'iso.view',
            'iso.manage',
        ]);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'iso.documents.manage',
            'iso.manage',
        ]);
    }

    public function update(User $user, $model = null): bool
    {
        return $user->hasAnyPermission([
            'iso.documents.manage',
            'iso.manage',
        ]);
    }

    /** Audit action — create/complete audits and add findings. */
    public function audit(User $user, $model = null): bool
    {
        return $user->hasAnyPermission([
            'iso.audits.manage',
            'iso.manage',
        ]);
    }

    public function delete(User $user, $model = null): bool
    {
        return $user->hasAnyPermission([
            'iso.documents.manage',
            'iso.manage',
        ]);
    }

    public function restore(User $user, $model = null): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDelete(User $user, $model = null): bool
    {
        return $user->hasRole('super_admin');
    }
}
