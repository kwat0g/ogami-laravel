<?php

declare(strict_types=1);

use App\Domains\Payroll\Models\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Payroll Approval Workflow — Feature Tests
|--------------------------------------------------------------------------
| Covers the post-computation approval chain:
|
|   Submit          → completed  →  submitted  (HR Manager, payroll.submit)
|   AccountingApprove→ submitted →  approved   (Accounting Manager, payroll.approve, SoD)
|   Post             → approved  →  posted     (Accounting Manager, payroll.post)
|
| SoD:
|   The run creator cannot accounting-approve their own run (policy enforces
|   created_by ≠ approving user).  A second service-level check throws
|   PR_SOD_VIOLATION (422) if somehow that path is reached with admin bypass.
--------------------------------------------------------------------------
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);

    // HR Manager — creates/locks/submits runs
    $this->hrManager = User::factory()->create(['password' => Hash::make('HRmgr!9876')]);
    $this->hrManager->assignRole('manager'); // Use new role name 'manager'
    $this->hrManager->givePermissionTo([
        'payroll.view',
        'payroll.initiate',
        'payroll.submit',
        'payroll.recall',
    ]);

    // Accounting Manager — approves and posts; deliberately a different user from hrManager
    $this->accountingManager = User::factory()->create(['password' => Hash::make('ACmgr!9876')]);
    $this->accountingManager->assignRole('officer'); // Use new role name 'officer'
    $this->accountingManager->givePermissionTo([
        'payroll.view',
        'payroll.approve',
        'payroll.acctg_approve', // Explicitly needed for accounting-approve
        'payroll.post',
    ]);
});

/** Factory helper — a run already in the requested status, created by hrManager. */
function makeRun(string $status, array $extra = []): PayrollRun
{
    return PayrollRun::factory()->create(array_merge([
        'status' => $status,
        'created_by' => test()->hrManager->id,
        'cutoff_start' => '2026-06-01',
        'cutoff_end' => '2026-06-15',
        'pay_date' => '2026-06-20',
        'run_type' => 'regular',
    ], $extra));
}

// ── PATCH /api/v1/payroll/runs/{id}/submit ───────────────────────────────

describe('PATCH .../submit', function () {
    it('transitions a completed run to submitted', function () {
        $run = makeRun('completed');

        $response = $this->actingAs($this->hrManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/submit");

        $response->assertOk()
            ->assertJsonPath('run.status', 'submitted');

        expect($run->fresh()->status)->toBe('submitted')
            ->and($run->fresh()->submitted_by)->toBe($this->hrManager->id);
    });

    it('returns 403 when user lacks payroll.submit permission', function () {
        $noPermUser = User::factory()->create();

        $run = makeRun('completed');

        $this->actingAs($noPermUser)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/submit")
            ->assertStatus(403);
    });

    it('returns 401 for unauthenticated request', function () {
        $run = makeRun('completed');

        $this->patchJson("/api/v1/payroll/runs/{$run->ulid}/submit")
            ->assertStatus(401);
    });

    it('returns 422 when run is not in completed status', function () {
        $run = makeRun('draft');

        $this->actingAs($this->hrManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/submit")
            ->assertStatus(422);
    });
});

// ── PATCH /api/v1/payroll/runs/{id}/accounting-approve ───────────────────

describe('PATCH .../accounting-approve', function () {
    it('transitions a submitted run to approved', function () {
        $run = makeRun('submitted');

        $response = $this->actingAs($this->accountingManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/accounting-approve");

        $response->assertOk()
            ->assertJsonPath('run.status', 'approved');

        expect($run->fresh()->status)->toBe('approved');
    });

    it('returns 403 when the policy SoD check blocks the run creator', function () {
        // hrManager is the run creator; they should NOT be able to accounting-approve
        $run = makeRun('submitted');

        $this->actingAs($this->hrManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/accounting-approve")
            ->assertStatus(403);

        expect($run->fresh()->status)->toBe('submitted'); // unchanged
    });

    it('returns 403 when user lacks payroll.acctg_approve permission', function () {
        $noPermUser = User::factory()->create();

        $run = makeRun('submitted');

        $this->actingAs($noPermUser)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/accounting-approve")
            ->assertStatus(403);
    });

    it('returns 422 when run is not in submitted status', function () {
        $run = makeRun('completed');

        $this->actingAs($this->accountingManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/accounting-approve")
            ->assertStatus(422);
    });
});

// ── PATCH /api/v1/payroll/runs/{id}/post ─────────────────────────────────

describe('PATCH .../post', function () {
    it('transitions an approved run to posted', function () {
        $run = makeRun('approved');

        $response = $this->actingAs($this->accountingManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/post");

        $response->assertOk()
            ->assertJsonPath('run.status', 'posted');

        $fresh = $run->fresh();
        expect($fresh->status)->toBe('posted')
            ->and($fresh->posted_at)->not->toBeNull();
    });

    it('returns 403 when user lacks payroll.post permission', function () {
        $noPermUser = User::factory()->create();

        $run = makeRun('approved');

        $this->actingAs($noPermUser)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/post")
            ->assertStatus(403);
    });

    it('returns 422 when run is not in approved status', function () {
        $run = makeRun('submitted');

        $this->actingAs($this->accountingManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/post")
            ->assertStatus(422);
    });

    it('run is immutable after posting — further transition attempts return 422', function () {
        $run = makeRun('approved');

        $this->actingAs($this->accountingManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/post")
            ->assertOk();

        // Try to post again → invalid transition
        $this->actingAs($this->accountingManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/post")
            ->assertStatus(422);
    });
});

// ── Full approval chain (integration smoke test) ─────────────────────────

describe('Full approval chain smoke test', function () {
    it('walks completed → submitted → approved → posted in sequence', function () {
        $run = makeRun('completed');

        // Step 1: HR submits for accounting review
        $this->actingAs($this->hrManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/submit")
            ->assertOk()
            ->assertJsonPath('run.status', 'submitted');

        // Step 2: Accounting Manager approves
        $this->actingAs($this->accountingManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/accounting-approve")
            ->assertOk()
            ->assertJsonPath('run.status', 'approved');

        // Step 3: Post to GL
        $this->actingAs($this->accountingManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/post")
            ->assertOk()
            ->assertJsonPath('run.status', 'posted');

        $final = $run->fresh();
        expect($final->status)->toBe('posted')
            ->and($final->submitted_by)->toBe($this->hrManager->id)
            ->and($final->posted_at)->not->toBeNull();
    });
});

// ── Service-level SoD (PR_SOD_VIOLATION at 422) ───────────────────────────

describe('Service-level SoD guard (PR_SOD_VIOLATION)', function () {
    it('throws 422 with PR_SOD_VIOLATION when admin tries to approve their own run', function () {
        // Admin bypasses the policy `before()` hook,
        // but the service itself enforces the same-creator check.
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $run = PayrollRun::factory()->create([
            'status' => 'submitted',
            'created_by' => $admin->id,
            'cutoff_start' => '2026-06-01',
            'cutoff_end' => '2026-06-15',
            'pay_date' => '2026-06-20',
            'run_type' => 'regular',
        ]);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/accounting-approve");

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'PR_SOD_VIOLATION');
    });
});
