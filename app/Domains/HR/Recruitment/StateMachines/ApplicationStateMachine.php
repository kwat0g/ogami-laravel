<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\StateMachines;

use App\Domains\HR\Recruitment\Enums\ApplicationStatus;
use App\Domains\HR\Recruitment\Models\Application;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Application screening state machine.
 *
 * States & transitions:
 *   new          -> under_review, withdrawn
 *   under_review -> shortlisted, rejected, withdrawn
 *   shortlisted  -> rejected, withdrawn
 *   rejected     -> (terminal)
 *   withdrawn    -> (terminal)
 */
final class ApplicationStateMachine
{
    public function transition(Application $application, ApplicationStatus $toStatus): void
    {
        $from = $application->status;

        if (! $from->canTransitionTo($toStatus)) {
            throw new InvalidStateTransitionException(
                'Application',
                $from->value,
                $toStatus->value,
            );
        }

        $application->status = $toStatus;
    }

    public function isAllowed(ApplicationStatus $from, ApplicationStatus $to): bool
    {
        return $from->canTransitionTo($to);
    }
}
