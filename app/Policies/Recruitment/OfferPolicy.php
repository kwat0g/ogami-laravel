<?php

declare(strict_types=1);

namespace App\Policies\Recruitment;

use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class OfferPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.offers.view');
    }

    public function view(User $user, JobOffer $offer): bool
    {
        return $user->hasPermissionTo('recruitment.offers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('recruitment.offers.create');
    }

    public function update(User $user, JobOffer $offer): bool
    {
        return $user->hasPermissionTo('recruitment.offers.create');
    }

    public function send(User $user, JobOffer $offer): bool
    {
        return $user->hasPermissionTo('recruitment.offers.send');
    }
}
