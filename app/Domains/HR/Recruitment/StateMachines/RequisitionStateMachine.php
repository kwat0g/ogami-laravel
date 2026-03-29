<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\StateMachines;

use App\Domains\HR\Recruitment\Enums\RequisitionStatus;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Requisition lifecycle state machine.
 *
 * States & transitions:
 *   draft            -> pending_approval, cancelled
 *   pending_approval -> approved, rejected, cancelled
 *   approved         -> open, cancelled
 *   rejected         -> draft (resubmit)
 *   open             -> on_hold, closed
 *   on_hold          -> open, closed, cancelled
 *   closed           -> (terminal)
 *   cancelled        -> (terminal)
 */
final class RequisitionStateMachine
{
    public function transition(JobRequisition $requisition, RequisitionStatus $toStatus): void
    {
        $from = $requisition->status;

        if (! $from->canTransitionTo($toStatus)) {
            throw new InvalidStateTransitionException(
                'JobRequisition',
                $from->value,
                $toStatus->value,
            );
        }

        $requisition->status = $toStatus;
    }

    public function isAllowed(RequisitionStatus $from, RequisitionStatus $to): bool
    {
        return $from->canTransitionTo($to);
    }
}
