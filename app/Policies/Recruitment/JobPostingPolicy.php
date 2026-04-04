<?php

declare(strict_types=1);

namespace App\Policies\Recruitment;

use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class JobPostingPolicy
{
    use HandlesAuthorization;

    private function isHrManager(User $user): bool
    {
        return $user->hasRole('manager')
            && $user->departments()->where('code', 'HR')->exists();
    }

    private function canAccessRecruitment(User $user): bool
    {
        $isHrOfficer = $user->hasRole('officer')
            && $user->departments()->where('code', 'HR')->exists();
        $isHrHead = $user->hasRole('head')
            && $user->departments()->where('code', 'HR')->exists();

        return $this->isHrManager($user) || $isHrOfficer || $isHrHead;
    }

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->canAccessRecruitment($user)
            || $user->hasPermissionTo('recruitment.postings.view');
    }

    public function view(User $user, JobPosting $posting): bool
    {
        return $this->canAccessRecruitment($user)
            || $user->hasPermissionTo('recruitment.postings.view');
    }

    public function create(User $user): bool
    {
        return $this->isHrManager($user)
            || ($this->isHrManager($user) && $user->hasPermissionTo('recruitment.postings.create'));
    }

    public function update(User $user, JobPosting $posting): bool
    {
        return $this->isHrManager($user)
            || ($this->isHrManager($user) && $user->hasPermissionTo('recruitment.postings.create'));
    }

    public function publish(User $user, JobPosting $posting): bool
    {
        return $this->isHrManager($user)
            || ($this->isHrManager($user) && $user->hasPermissionTo('recruitment.postings.publish'));
    }

    public function close(User $user, JobPosting $posting): bool
    {
        return $this->isHrManager($user)
            || ($this->isHrManager($user) && $user->hasPermissionTo('recruitment.postings.close'));
    }
}
