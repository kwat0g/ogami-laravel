<?php

declare(strict_types=1);

namespace App\Domains\Sales\StateMachines;

use App\Domains\Sales\Models\Quotation;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Quotation state machine.
 *
 * States:
 *   draft              → Initial creation
 *   sent               → Sent to customer
 *   accepted           → Customer accepted
 *   converted_to_order → Sales order created from quotation
 *   rejected           → Customer rejected
 *   expired            → Past validity date
 */
final class QuotationStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['sent', 'rejected'],
        'sent' => ['accepted', 'rejected', 'expired'],
        'accepted' => ['converted_to_order'],
        'converted_to_order' => [],  // terminal
        'rejected' => [],            // terminal
        'expired' => [],             // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(Quotation $quotation, string $toState): void
    {
        $fromState = $quotation->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('Quotation', $fromState, $toState);
        }

        $quotation->status = $toState;
    }

    public function isAllowed(string $fromState, string $toState): bool
    {
        return in_array($toState, self::TRANSITIONS[$fromState] ?? [], true);
    }

    /** @return list<string> */
    public function allowedTransitions(string $currentState): array
    {
        return self::TRANSITIONS[$currentState] ?? [];
    }
}
