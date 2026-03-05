<?php

declare(strict_types=1);

namespace App\Domains\QC\Policies;

use App\Domains\QC\Models\NonConformanceReport;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class NcrPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool  { return $user->can('qc.ncr.view'); }
    public function view(User $user): bool      { return $user->can('qc.ncr.view'); }
    public function create(User $user): bool    { return $user->can('qc.ncr.create'); }
    public function issueCapa(User $user): bool { return $user->can('qc.ncr.create'); }
    public function close(User $user): bool     { return $user->can('qc.ncr.close'); }
}
