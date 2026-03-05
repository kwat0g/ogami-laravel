<?php

declare(strict_types=1);

namespace App\Domains\AR\Policies;

use App\Domains\AR\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Customer Policy.
 *
 * Permissions: customers.view | .create | .update | .archive
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
        return $user->hasPermissionTo('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        if (! $customer->is_active) {
            return false;
        }

        return $user->hasPermissionTo('customers.update');
    }

    public function archive(User $user, Customer $customer): bool
    {
        return $user->hasPermissionTo('customers.archive');
    }
}
