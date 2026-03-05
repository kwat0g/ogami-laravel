<?php

declare(strict_types=1);

namespace App\Domains\ISO\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ISOPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool  { return $user->hasPermissionTo('iso.view'); }
    public function view(User $user): bool     { return $user->hasPermissionTo('iso.view'); }
    public function create(User $user): bool   { return $user->hasPermissionTo('iso.manage'); }
    public function update(User $user): bool   { return $user->hasPermissionTo('iso.manage'); }
    public function audit(User $user): bool    { return $user->hasPermissionTo('iso.audit'); }
}
