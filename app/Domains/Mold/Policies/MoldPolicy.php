<?php

declare(strict_types=1);

namespace App\Domains\Mold\Policies;

use App\Domains\Mold\Models\MoldMaster;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class MoldPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool  { return $user->can('mold.view'); }
    public function view(User $user): bool      { return $user->can('mold.view'); }
    public function create(User $user): bool    { return $user->can('mold.manage'); }
    public function update(User $user): bool    { return $user->can('mold.manage'); }
    public function logShots(User $user): bool  { return $user->can('mold.log_shots'); }
}
