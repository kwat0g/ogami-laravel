<?php

declare(strict_types=1);

use App\Domains\Payroll\Models\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Payroll Run Lifecycle — Feature Tests
|--------------------------------------------------------------------------
| Covers the payroll run state machine at the HTTP layer:
|
|   Store  → status: draft
|   Lock   → status: locked (dispatches computation batch)
|   Approve→ status: completed (SoD-gated)
|   Cancel (PATCH)  → status: cancelled
|   Archive (DELETE)→ soft-deleted (cancelled/rejected only)
|
| Pre-condition:
|   - User must have `payroll.initiate` to create a run
|   - Approve route has `sod:payroll_runs,approve` middleware
--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);

    // Test actor with explicit payroll permissions used in this file.
    $this->hrManager = User::factory()->create(['password' => Hash::make('HRmgr!9876')]);
    $this->hrManager->givePermissionTo('payroll.initiate');
    $this->hrManager->givePermissionTo('payroll.view');
});

function validRunPayload(array $overrides = []): array
{
    return array_merge([
        'cutoff_start' => '2026-02-01',
        'cutoff_end' => '2026-02-15',
        'pay_date' => '2026-02-20',
        'run_type' => 'regular',
    ], $overrides);
}

// ── POST /api/v1/payroll/runs ──────────────────────────────────────────────

describe('POST /api/v1/payroll/runs', function () {
    it('creates a payroll run in draft status with valid payload', function () {
        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/payroll/runs', validRunPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.run_type', 'regular');
    });

    it('rejects when cutoff_end is before cutoff_start', function () {
        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/payroll/runs', validRunPayload([
                'cutoff_end' => '2026-01-31',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cutoff_end']);
    });

    it('returns 403 when user lacks payroll.initiate permission', function () {
        // No role assigned — user has zero permissions
        $noPermUser = User::factory()->create();

        $this->actingAs($noPermUser)
            ->postJson('/api/v1/payroll/runs', validRunPayload())
            ->assertStatus(403);
    });

    it('returns 401 for unauthenticated request', function () {
        $this->postJson('/api/v1/payroll/runs', validRunPayload())
            ->assertStatus(401);
    });
});

// ── GET /api/v1/payroll/runs/{id} ─────────────────────────────────────────

describe('GET /api/v1/payroll/runs/{id}', function () {
    it('returns run details for an authorised user', function () {
        $run = PayrollRun::factory()->create([
            'created_by' => $this->hrManager->id,
            'cutoff_start' => '2026-02-01',
            'cutoff_end' => '2026-02-15',
            'pay_date' => '2026-02-20',
            'status' => 'draft',
            'run_type' => 'regular',
        ]);

        $response = $this->actingAs($this->hrManager)
            ->getJson("/api/v1/payroll/runs/{$run->ulid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $run->id);
    });

    it('returns 404 for non-existent payroll run', function () {
        $this->actingAs($this->hrManager)
            ->getJson('/api/v1/payroll/runs/99999')
            ->assertStatus(404);
    });
});

// ── GET /api/v1/payroll/runs ───────────────────────────────────────────────

describe('GET /api/v1/payroll/runs', function () {
    it('returns paginated list of runs', function () {
        // Use hard-coded non-overlapping date ranges to avoid exclusion constraint
        PayrollRun::factory()->create([
            'created_by' => $this->hrManager->id,
            'cutoff_start' => '2025-03-01',
            'cutoff_end' => '2025-03-15',
            'pay_date' => '2025-03-20',
        ]);
        PayrollRun::factory()->create([
            'created_by' => $this->hrManager->id,
            'cutoff_start' => '2025-04-01',
            'cutoff_end' => '2025-04-15',
            'pay_date' => '2025-04-20',
        ]);

        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/payroll/runs');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    });
});

// ── PATCH /api/v1/payroll/runs/{id}/cancel ────────────────────────────────

describe('PATCH /api/v1/payroll/runs/{id}/cancel', function () {
    it('cancels a draft run (sets status to cancelled)', function () {
        $run = PayrollRun::factory()->create([
            'created_by' => $this->hrManager->id,
            'status' => 'DRAFT',
            'cutoff_start' => '2025-05-01',
            'cutoff_end' => '2025-05-15',
            'pay_date' => '2025-05-20',
        ]);

        $response = $this->actingAs($this->hrManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/cancel");

        $response->assertStatus(200);

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $run->id,
            'status' => 'cancelled',
        ]);
    });

    it('rejects cancelling a disbursed run', function () {
        $run = PayrollRun::factory()->create([
            'created_by' => $this->hrManager->id,
            'status' => 'DISBURSED',
            'cutoff_start' => '2025-06-01',
            'cutoff_end' => '2025-06-15',
            'pay_date' => '2025-06-20',
        ]);

        $this->actingAs($this->hrManager)
            ->patchJson("/api/v1/payroll/runs/{$run->ulid}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'PR_NOT_CANCELLABLE');

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $run->id,
            'status' => 'DISBURSED',
            'deleted_at' => null,
        ]);
    });
});

// ── DELETE /api/v1/payroll/runs/{id} (archive) ────────────────────────────

describe('DELETE /api/v1/payroll/runs/{id}', function () {
    it('archives a cancelled run', function () {
        $run = PayrollRun::factory()->create([
            'created_by' => $this->hrManager->id,
            'status' => 'cancelled',
            'cutoff_start' => '2025-07-01',
            'cutoff_end' => '2025-07-15',
            'pay_date' => '2025-07-20',
        ]);

        $this->actingAs($this->hrManager)
            ->deleteJson("/api/v1/payroll/runs/{$run->ulid}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Payroll run archived.');

        $this->assertSoftDeleted('payroll_runs', ['id' => $run->id]);
    });

    it('archives a rejected run', function () {
        $run = PayrollRun::factory()->create([
            'created_by' => $this->hrManager->id,
            'status' => 'REJECTED',
            'cutoff_start' => '2025-08-01',
            'cutoff_end' => '2025-08-15',
            'pay_date' => '2025-08-20',
        ]);

        $this->actingAs($this->hrManager)
            ->deleteJson("/api/v1/payroll/runs/{$run->ulid}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Payroll run archived.');

        $this->assertSoftDeleted('payroll_runs', ['id' => $run->id]);
    });

    it('rejects archiving a disbursed run', function () {
        $run = PayrollRun::factory()->create([
            'created_by' => $this->hrManager->id,
            'status' => 'DISBURSED',
            'cutoff_start' => '2025-09-01',
            'cutoff_end' => '2025-09-15',
            'pay_date' => '2025-09-20',
        ]);

        $this->actingAs($this->hrManager)
            ->deleteJson("/api/v1/payroll/runs/{$run->ulid}")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'PR_ARCHIVE_NOT_ALLOWED');

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $run->id,
            'status' => 'DISBURSED',
            'deleted_at' => null,
        ]);
    });

    it('rejects archiving a published run', function () {
        $run = PayrollRun::factory()->create([
            'created_by' => $this->hrManager->id,
            'status' => 'PUBLISHED',
            'cutoff_start' => '2025-10-01',
            'cutoff_end' => '2025-10-15',
            'pay_date' => '2025-10-20',
        ]);

        $this->actingAs($this->hrManager)
            ->deleteJson("/api/v1/payroll/runs/{$run->ulid}")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'PR_ARCHIVE_NOT_ALLOWED');

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $run->id,
            'status' => 'PUBLISHED',
            'deleted_at' => null,
        ]);
    });
});
