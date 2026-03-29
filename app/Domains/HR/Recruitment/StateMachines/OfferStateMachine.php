<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\StateMachines;

use App\Domains\HR\Recruitment\Enums\OfferStatus;
use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Offer lifecycle state machine.
 *
 * States & transitions:
 *   draft     -> sent, withdrawn
 *   sent      -> accepted, rejected, expired, withdrawn
 *   accepted  -> (terminal)
 *   rejected  -> (terminal)
 *   expired   -> (terminal)
 *   withdrawn -> (terminal)
 */
final class OfferStateMachine
{
    public function transition(JobOffer $offer, OfferStatus $toStatus): void
    {
        $from = $offer->status;

        if (! $from->canTransitionTo($toStatus)) {
            throw new InvalidStateTransitionException(
                'JobOffer',
                $from->value,
                $toStatus->value,
            );
        }

        $offer->status = $toStatus;
    }

    public function isAllowed(OfferStatus $from, OfferStatus $to): bool
    {
        return $from->canTransitionTo($to);
    }
}
