<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Policies;

use App\Domains\Maintenance\Models\Equipment;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class MaintenancePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool  { return $user->can('maintenance.view'); }
    public function view(User $user): bool      { return $user->can('maintenance.view'); }
    public function create(User $user): bool    { return $user->can('maintenance.manage'); }
    public function update(User $user): bool    { return $user->can('maintenance.manage'); }
}
