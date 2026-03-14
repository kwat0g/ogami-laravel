<?php

declare(strict_types=1);

namespace App\Domains\QC\Policies;

use App\Domains\QC\Models\InspectionTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class InspectionTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('qc.templates.view');
    }

    public function view(User $user, InspectionTemplate $template): bool
    {
        return $user->can('qc.templates.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qc.templates.manage');
    }

    public function update(User $user, InspectionTemplate $template): bool
    {
        return $user->can('qc.templates.manage');
    }

    public function delete(User $user, InspectionTemplate $template): bool
    {
        return $user->can('qc.templates.manage');
    }
}
