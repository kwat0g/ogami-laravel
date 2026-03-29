<?php

declare(strict_types=1);

namespace App\Domains\Attendance\StateMachines;

use App\Domains\Attendance\Models\AttendanceCorrectionRequest;
use App\Shared\Exceptions\DomainException;

/**
 * State machine for attendance correction requests.
 *
 * draft → submitted → approved / rejected
 * rejected → draft (employee may revise and resubmit)
 * draft → cancelled
 */
final class CorrectionRequestStateMachine
{
    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['approved', 'rejected'],
        'approved' => [],
        'rejected' => ['draft'],
        'cancelled' => [],
    ];

    public function transition(AttendanceCorrectionRequest $request, string $toState): void
    {
        $fromState = $request->status->value ?? $request->status;

        if (! $this->isAllowed($fromState, $toState)) {
            throw new DomainException(
                "Cannot transition correction request from '{$fromState}' to '{$toState}'.",
                'INVALID_CORRECTION_STATUS_TRANSITION',
                422,
            );
        }

        $request->status = $toState;
        $request->save();
    }

    public function isAllowed(string $fromState, string $toState): bool
    {
        return in_array($toState, self::TRANSITIONS[$fromState] ?? [], true);
    }
}
