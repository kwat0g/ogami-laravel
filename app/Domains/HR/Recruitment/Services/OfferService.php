<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Services;

use App\Domains\HR\Recruitment\Enums\OfferStatus;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Domains\HR\Recruitment\StateMachines\OfferStateMachine;
use App\Models\User;
use App\Notifications\Recruitment\OfferAcceptedNotification;
use App\Notifications\Recruitment\OfferRejectedNotification;
use App\Notifications\Recruitment\OfferSentNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class OfferService implements ServiceContract
{
    public function __construct(
        private readonly OfferStateMachine $stateMachine,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<JobOffer>
     */
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return JobOffer::with(['application.candidate', 'offeredPosition', 'offeredDepartment', 'preparer'])
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['search']), fn ($q) => $q->where('offer_number', 'ILIKE', "%{$filters['search']}%"))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function show(JobOffer $offer): JobOffer
    {
        return $offer->load([
            'application.candidate',
            'application.posting.requisition',
            'offeredPosition',
            'offeredDepartment',
            'preparer',
            'approver',
        ]);
    }

    public function prepareOffer(Application $application, array $data, User $actor): JobOffer
    {
        // Check if there's already an active offer
        $existingOffer = $application->offer;
        if ($existingOffer && in_array($existingOffer->status, [OfferStatus::Draft, OfferStatus::Sent])) {
            throw new DomainException(
                'This application already has an active offer. Withdraw it first.',
                'ACTIVE_OFFER_EXISTS',
                422,
                ['existing_offer_id' => $existingOffer->id],
            );
        }

        return DB::transaction(function () use ($application, $data, $actor): JobOffer {
            return JobOffer::create([
                'application_id' => $application->id,
                'offered_position_id' => $data['offered_position_id'],
                'offered_department_id' => $data['offered_department_id'],
                'offered_salary' => $data['offered_salary'],
                'employment_type' => $data['employment_type'],
                'start_date' => $data['start_date'],
                'offer_letter_path' => $data['offer_letter_path'] ?? null,
                'status' => OfferStatus::Draft->value,
                'expires_at' => $data['expires_at'] ?? null,
                'prepared_by' => $actor->id,
            ]);
        });
    }

    public function update(JobOffer $offer, array $data): JobOffer
    {
        if ($offer->status !== OfferStatus::Draft) {
            throw new DomainException(
                'Can only edit draft offers.',
                'OFFER_NOT_EDITABLE',
                422,
                ['current_status' => $offer->status->value],
            );
        }

        return DB::transaction(function () use ($offer, $data): JobOffer {
            $offer->update($data);

            return $offer->fresh();
        });
    }

    public function sendOffer(JobOffer $offer, User $actor): JobOffer
    {
        $result = DB::transaction(function () use ($offer, $actor): JobOffer {
            $this->stateMachine->transition($offer, OfferStatus::Sent);
            $offer->sent_at = now();
            $offer->approved_by = $actor->id;
            $offer->expires_at = $offer->expires_at ?? now()->addDays(7);
            $offer->save();

            return $offer;
        });

        // Notify candidate via on-demand mail
        $candidate = $result->application?->candidate;
        if ($candidate?->email) {
            Notification::route('mail', $candidate->email)
                ->notify(OfferSentNotification::fromModel($result->load(['application.candidate', 'offeredPosition'])));
        }

        // Notify HR managers
        $hrManagers = User::whereHas('roles', fn ($q) => $q->where('name', 'manager'))
            ->whereHas('departments', fn ($q) => $q->where('code', 'HR'))
            ->get();
        foreach ($hrManagers as $mgr) {
            $mgr->notify(OfferSentNotification::fromModel($result));
        }

        return $result;
    }

    public function acceptOffer(JobOffer $offer): JobOffer
    {
        $result = DB::transaction(function () use ($offer): JobOffer {
            $this->stateMachine->transition($offer, OfferStatus::Accepted);
            $offer->responded_at = now();
            $offer->save();

            return $offer;
        });

        // Notify HR + requester
        $hrManagers = User::whereHas('roles', fn ($q) => $q->where('name', 'manager'))
            ->whereHas('departments', fn ($q) => $q->where('code', 'HR'))
            ->get();
        foreach ($hrManagers as $mgr) {
            $mgr->notify(OfferAcceptedNotification::fromModel($result->load(['application.candidate', 'offeredPosition'])));
        }

        return $result;
    }

    public function rejectOffer(JobOffer $offer, string $reason): JobOffer
    {
        $result = DB::transaction(function () use ($offer, $reason): JobOffer {
            $this->stateMachine->transition($offer, OfferStatus::Rejected);
            $offer->responded_at = now();
            $offer->rejection_reason = $reason;
            $offer->save();

            return $offer;
        });

        // Notify HR
        $hrManagers = User::whereHas('roles', fn ($q) => $q->where('name', 'manager'))
            ->whereHas('departments', fn ($q) => $q->where('code', 'HR'))
            ->get();
        foreach ($hrManagers as $mgr) {
            $mgr->notify(OfferRejectedNotification::fromModel($result->load(['application.candidate', 'offeredPosition'])));
        }

        return $result;
    }

    public function withdrawOffer(JobOffer $offer, User $actor): JobOffer
    {
        return DB::transaction(function () use ($offer): JobOffer {
            $this->stateMachine->transition($offer, OfferStatus::Withdrawn);
            $offer->save();

            return $offer;
        });
    }

    public function expireOffer(JobOffer $offer): JobOffer
    {
        if ($offer->status !== OfferStatus::Sent) {
            return $offer;
        }

        return DB::transaction(function () use ($offer): JobOffer {
            $this->stateMachine->transition($offer, OfferStatus::Expired);
            $offer->save();

            return $offer;
        });
    }
}
