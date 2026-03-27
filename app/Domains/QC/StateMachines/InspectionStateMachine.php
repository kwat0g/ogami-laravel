<?php

declare(strict_types=1);

namespace App\Domains\QC\StateMachines;

use App\Domains\QC\Models\Inspection;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * QC Inspection state machine.
 *
 * States:
 *   open     → Inspection created / scheduled
 *   passed   → Inspection passed QC — terminal
 *   failed   → Inspection failed QC — terminal
 *   on_hold  → Inspection on hold pending further review
 *   voided   → Inspection voided — terminal
 */
final class InspectionStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'open' => ['passed', 'failed', 'on_hold', 'voided'],
        'on_hold' => ['open', 'passed', 'failed', 'voided'],
        'passed' => [],  // terminal
        'failed' => [],  // terminal
        'voided' => [],  // terminal
    ];

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(Inspection $inspection, string $toState): void
    {
        $fromState = $inspection->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new InvalidStateTransitionException('Inspection', $fromState, $toState);
        }

        $inspection->status = $toState;
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
