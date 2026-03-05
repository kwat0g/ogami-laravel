<?php

declare(strict_types=1);

use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Payroll\StateMachines\PayrollRunStateMachine;
use App\Shared\Exceptions\DomainException;

/*
|--------------------------------------------------------------------------
| PayrollRunStateMachineTest
|--------------------------------------------------------------------------
| Sprint 10 — Verifies the expanded 9-status state machine:
|   draft | locked | processing | completed | submitted | approved | posted
|   failed | cancelled
|--------------------------------------------------------------------------
*/

function makeMachine(): PayrollRunStateMachine
{
    return new PayrollRunStateMachine;
}

function runWithStatus(string $status): PayrollRun
{
    $run = new PayrollRun;
    $run->status = $status;

    return $run;
}

// ── canTransition ─────────────────────────────────────────────────────────────

describe('canTransition — valid paths', function () {
    it('draft → locked', fn () => expect(makeMachine()->canTransition(runWithStatus('draft'), 'locked'))->toBeTrue());
    it('draft → cancelled', fn () => expect(makeMachine()->canTransition(runWithStatus('draft'), 'cancelled'))->toBeTrue());
    it('locked → processing', fn () => expect(makeMachine()->canTransition(runWithStatus('locked'), 'processing'))->toBeTrue());
    it('locked → draft (un-lock)', fn () => expect(makeMachine()->canTransition(runWithStatus('locked'), 'draft'))->toBeTrue());
    it('locked → cancelled', fn () => expect(makeMachine()->canTransition(runWithStatus('locked'), 'cancelled'))->toBeTrue());
    it('processing → completed', fn () => expect(makeMachine()->canTransition(runWithStatus('processing'), 'completed'))->toBeTrue());
    it('processing → failed', fn () => expect(makeMachine()->canTransition(runWithStatus('processing'), 'failed'))->toBeTrue());
    it('processing → cancelled', fn () => expect(makeMachine()->canTransition(runWithStatus('processing'), 'cancelled'))->toBeTrue());
    it('completed → submitted', fn () => expect(makeMachine()->canTransition(runWithStatus('completed'), 'submitted'))->toBeTrue());
    it('completed → cancelled', fn () => expect(makeMachine()->canTransition(runWithStatus('completed'), 'cancelled'))->toBeTrue());
    it('submitted → approved', fn () => expect(makeMachine()->canTransition(runWithStatus('submitted'), 'approved'))->toBeTrue());
    it('submitted → completed (rejection/recall)', fn () => expect(makeMachine()->canTransition(runWithStatus('submitted'), 'completed'))->toBeTrue());
    it('approved → posted', fn () => expect(makeMachine()->canTransition(runWithStatus('approved'), 'posted'))->toBeTrue());
    it('failed → locked (retry)', fn () => expect(makeMachine()->canTransition(runWithStatus('failed'), 'locked'))->toBeTrue());
});

describe('canTransition — blocked paths', function () {
    it('draft cannot go directly to processing', fn () => expect(makeMachine()->canTransition(runWithStatus('draft'), 'processing'))->toBeFalse());
    it('completed is no longer terminal — cannot go to approved directly', fn () => expect(makeMachine()->canTransition(runWithStatus('completed'), 'approved'))->toBeFalse());
    it('posted is terminal — no outgoing transitions', fn () => expect(makeMachine()->canTransition(runWithStatus('posted'), 'approved'))->toBeFalse());
    it('cancelled is terminal', fn () => expect(makeMachine()->canTransition(runWithStatus('cancelled'), 'draft'))->toBeFalse());
    it('approved cannot skip to cancelled', fn () => expect(makeMachine()->canTransition(runWithStatus('approved'), 'cancelled'))->toBeFalse());
    it('failed cannot jump to completed', fn () => expect(makeMachine()->canTransition(runWithStatus('failed'), 'completed'))->toBeFalse());
    it('submitted cannot jump to posted directly', fn () => expect(makeMachine()->canTransition(runWithStatus('submitted'), 'posted'))->toBeFalse());
});

// ── allowedNext ───────────────────────────────────────────────────────────────

describe('allowedNext', function () {
    it('returns submitted and cancelled from completed', function () {
        $allowed = makeMachine()->allowedNext(runWithStatus('completed'));
        expect($allowed)->toContain('submitted');
        expect($allowed)->toContain('cancelled');
    });

    it('returns empty array from posted', function () {
        expect(makeMachine()->allowedNext(runWithStatus('posted')))->toBe([]);
    });

    it('returns locked and PRE_RUN_CHECKED from failed (retry paths)', function () {
        $allowed = makeMachine()->allowedNext(runWithStatus('failed'));
        expect($allowed)->toContain('locked');
        expect($allowed)->toContain('PRE_RUN_CHECKED');
    });
});

// ── transition (requires DB for save) ────────────────────────────────────────

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('transition', function () {
    beforeEach(fn () => $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0));

    it('throws PR_INVALID_TRANSITION for an illegal status jump', function () {
        $run = PayrollRun::factory()->create([
            'status' => 'draft',
            'cutoff_start' => '2026-06-01',
            'cutoff_end' => '2026-06-15',
            'pay_date' => '2026-06-20',
        ]);

        expect(fn () => makeMachine()->transition($run, 'approved'))
            ->toThrow(DomainException::class);
    });

    it('transitions draft → locked and stamps locked_at', function () {
        $run = PayrollRun::factory()->create([
            'status' => 'draft',
            'cutoff_start' => '2026-07-01',
            'cutoff_end' => '2026-07-15',
            'pay_date' => '2026-07-20',
        ]);

        makeMachine()->transition($run, 'locked');

        expect($run->fresh()->status)->toBe('locked')
            ->and($run->fresh()->locked_at)->not->toBeNull();
    });

    it('transitions approved → posted and stamps posted_at', function () {
        $run = PayrollRun::factory()->create([
            'status' => 'approved',
            'cutoff_start' => '2026-08-01',
            'cutoff_end' => '2026-08-15',
            'pay_date' => '2026-08-20',
        ]);

        makeMachine()->transition($run, 'posted');

        expect($run->fresh()->status)->toBe('posted')
            ->and($run->fresh()->posted_at)->not->toBeNull();
    });
});
