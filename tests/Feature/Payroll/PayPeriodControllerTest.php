<?php

declare(strict_types=1);

use App\Domains\Payroll\Models\PayPeriod;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Pay Period Controller — Feature Tests
|--------------------------------------------------------------------------
| Covers the full CRUD + close lifecycle for pay periods.
|
| Routes:
|   GET    /api/v1/payroll/periods
|   POST   /api/v1/payroll/periods
|   GET    /api/v1/payroll/periods/{id}
|   PATCH  /api/v1/payroll/periods/{id}/close
--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);

    $this->manager = User::factory()->create([
        'password' => Hash::make('Manager!123'),
    ]);
    $this->manager->assignRole('super_admin');
});

// ── Valid period payload ───────────────────────────────────────────────────

function validPeriodPayload(array $overrides = []): array
{
    return array_merge([
        'label' => 'February 2026 1st Cut',
        'cutoff_start' => '2026-02-01',
        'cutoff_end' => '2026-02-15',
        'pay_date' => '2026-02-20',
        'frequency' => 'semi_monthly',
    ], $overrides);
}

// ── Index ──────────────────────────────────────────────────────────────────

describe('GET /api/v1/payroll/periods', function () {
    it('returns a paginated list of pay periods', function () {
        $this->withoutExceptionHandling();
        PayPeriod::factory()->count(3)->create();

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/payroll/periods');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'total', 'current_page']);
    });

    it('filters by status=open', function () {
        PayPeriod::factory()->create(['status' => 'open']);
        PayPeriod::factory()->create(['status' => 'closed']);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/payroll/periods?status=open');

        $response->assertStatus(200);
        $data = $response->json('data');
        collect($data)->each(fn ($p) => expect($p['status'])->toBe('open'));
    });

    it('returns 401 for unauthenticated request', function () {
        $this->getJson('/api/v1/payroll/periods')
            ->assertStatus(401);
    });
});

// ── Store ──────────────────────────────────────────────────────────────────

describe('POST /api/v1/payroll/periods', function () {
    it('creates a new pay period with valid payload', function () {
        $response = $this->actingAs($this->manager)
            ->postJson('/api/v1/payroll/periods', validPeriodPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.label', 'February 2026 1st Cut');

        $this->assertDatabaseHas('pay_periods', [
            'label' => 'February 2026 1st Cut',
            'cutoff_start' => '2026-02-01',
            'status' => 'open',
        ]);
    });

    it('rejects when cutoff_end is before cutoff_start', function () {
        $response = $this->actingAs($this->manager)
            ->postJson('/api/v1/payroll/periods', validPeriodPayload([
                'cutoff_end' => '2026-01-31',  // before cutoff_start 2026-02-01
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cutoff_end']);
    });

    it('rejects when pay_date is before cutoff_end', function () {
        $response = $this->actingAs($this->manager)
            ->postJson('/api/v1/payroll/periods', validPeriodPayload([
                'pay_date' => '2026-02-10',  // before cutoff_end 2026-02-15
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pay_date']);
    });

    it('rejects invalid frequency value', function () {
        $response = $this->actingAs($this->manager)
            ->postJson('/api/v1/payroll/periods', validPeriodPayload([
                'frequency' => 'bi_weekly',  // not in allowed enum
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['frequency']);
    });

    it('requires label field', function () {
        $payload = validPeriodPayload();
        unset($payload['label']);

        $this->actingAs($this->manager)
            ->postJson('/api/v1/payroll/periods', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['label']);
    });

    it('returns 401 for unauthenticated request', function () {
        $this->postJson('/api/v1/payroll/periods', validPeriodPayload())
            ->assertStatus(401);
    });
});

// ── Show ───────────────────────────────────────────────────────────────────

describe('GET /api/v1/payroll/periods/{id}', function () {
    it('returns the requested pay period', function () {
        $period = PayPeriod::factory()->create(['label' => 'Test Period']);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/v1/payroll/periods/{$period->id}");

        $response->assertStatus(200)
            ->assertJsonPath('label', 'Test Period');
    });

    it('returns 404 for non-existent pay period', function () {
        $this->actingAs($this->manager)
            ->getJson('/api/v1/payroll/periods/99999')
            ->assertStatus(404);
    });
});

// ── Close ──────────────────────────────────────────────────────────────────

describe('PATCH /api/v1/payroll/periods/{id}/close', function () {
    it('closes an open pay period', function () {
        $period = PayPeriod::factory()->create(['status' => 'open']);

        $response = $this->actingAs($this->manager)
            ->patchJson("/api/v1/payroll/periods/{$period->id}/close");

        $response->assertStatus(200);

        $this->assertDatabaseHas('pay_periods', [
            'id' => $period->id,
            'status' => 'closed',
        ]);
    });

    it('returns 409 ALREADY_CLOSED when closing an already-closed period', function () {
        $period = PayPeriod::factory()->create(['status' => 'closed']);

        $response = $this->actingAs($this->manager)
            ->patchJson("/api/v1/payroll/periods/{$period->id}/close");

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'ALREADY_CLOSED');
    });

    it('returns 401 for unauthenticated request', function () {
        $period = PayPeriod::factory()->create(['status' => 'open']);

        $this->patchJson("/api/v1/payroll/periods/{$period->id}/close")
            ->assertStatus(401);
    });
});
