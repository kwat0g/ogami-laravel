<?php

declare(strict_types=1);

namespace App\Domains\AR\Policies;

use App\Domains\AR\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Customer Policy.
 *
 * Permissions: customers.view | .manage | .archive
 */
final class CustomerPolicy
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
        return $user->hasPermissionTo('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        if (! $customer->is_active) {
            return false;
        }

        return $user->hasPermissionTo('customers.manage');
    }

    public function archive(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('customers.archive');
    }

    /** Provision a client portal user account — admin / system user management only. */
    public function provisionAccount(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('system.manage_users');
    }
}
