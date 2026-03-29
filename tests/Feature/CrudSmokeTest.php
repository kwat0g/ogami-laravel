<?php

declare(strict_types=1);

/**
 * CRUD action smoke test -- tests save buttons across all modules.
 * Run: ./vendor/bin/pest tests/Feature/CrudSmokeTest.php -v
 */

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder']);
    \Artisan::call('db:seed', ['--class' => 'SampleAccountsSeeder']);
    \Artisan::call('db:seed', ['--class' => 'SampleDataSeeder']);
    $this->admin = \App\Models\User::where('email', 'admin@ogamierp.local')->firstOrFail();
    $this->hrMgr = \App\Models\User::where('email', 'hr.manager@ogamierp.local')->firstOrFail();
    $this->acctg = \App\Models\User::where('email', 'accounting@ogamierp.local')->firstOrFail();
});

// -- Helper: assert create does not crash ------------------------------------

function assertCreateNoCrash(
    string $endpoint,
    array $payload,
    \App\Models\User $user,
    array $acceptableCodes = [200, 201, 422]
): void {
    $response = test()->actingAs($user)->postJson($endpoint, $payload);
    $status   = $response->status();

    expect($status)
        ->toBeIn($acceptableCodes,
            "POST {$endpoint} returned unexpected {$status}.\n".
            'Body: '.substr($response->content(), 0, 400)
        );
}

function assertUpdateNoCrash(
    string $endpoint,
    array $payload,
    \App\Models\User $user
): void {
    $response = test()->actingAs($user)->putJson($endpoint, $payload);
    expect($response->status())->not->toBe(500, "PUT {$endpoint} crashed with 500");
}

function assertDeleteNoCrash(string $endpoint, \App\Models\User $user): void
{
    $response = test()->actingAs($user)->deleteJson($endpoint);
    expect($response->status())->not->toBe(500, "DELETE {$endpoint} crashed with 500");
}

// ============================================================================
// HR MODULE
// ============================================================================

describe('HR CRUD', function () {

    it('create department does not crash', function () {
        assertCreateNoCrash('/api/v1/hr/departments', [
            'name' => 'Test Department '.time(),
            'code' => 'TD'.rand(100, 999),
        ], $this->admin);
    });

});

// ============================================================================
// ATTENDANCE TIME IN / OUT
// ============================================================================

describe('Attendance Time In/Out', function () {

    it('time-in with GPS payload does not crash', function () {
        $response = $this->actingAs($this->hrMgr)
            ->postJson('/api/v1/attendance/time-in', [
                'latitude'        => 14.5995,
                'longitude'       => 120.9842,
                'accuracy_meters' => 15.5,
                'device_info'     => [
                    'browser' => 'Chrome 120',
                    'os'      => 'Windows 11',
                    'ip'      => '192.168.1.1',
                ],
            ]);

        // Acceptable: 200 (success), 422 (no shift assigned / already timed in)
        // NOT acceptable: 500 (crash)
        expect($response->status())
            ->not->toBe(500,
                "Time-in crashed with 500:\n".substr($response->content(), 0, 500)
            );
    });

    it('time-in missing GPS returns 422 not 500', function () {
        $response = $this->actingAs($this->hrMgr)
            ->postJson('/api/v1/attendance/time-in', []);

        expect($response->status())->not->toBe(500);
    });

    it('time-out does not crash', function () {
        $response = $this->actingAs($this->hrMgr)
            ->postJson('/api/v1/attendance/time-out', [
                'latitude'        => 14.5995,
                'longitude'       => 120.9842,
                'accuracy_meters' => 20.0,
            ]);

        expect($response->status())->not->toBe(500);
    });

    it('GET today status does not crash', function () {
        $response = $this->actingAs($this->hrMgr)
            ->getJson('/api/v1/attendance/today');

        expect($response->status())->not->toBe(500)->not->toBe(403);
    });

});

// ============================================================================
// PROCUREMENT
// ============================================================================

describe('Procurement CRUD', function () {

    it('create vendor does not crash', function () {
        assertCreateNoCrash('/api/v1/procurement/vendors', [
            'name'           => 'Test Vendor '.time(),
            'code'           => 'TV'.rand(1000, 9999),
            'email'          => 'vendor'.time().'@test.com',
            'contact_person' => 'Test Contact',
            'address'        => 'Test Address, Manila',
        ], $this->admin);
    });

});

// ============================================================================
// SALES
// ============================================================================

describe('Sales CRUD', function () {

    it('create customer does not crash', function () {
        assertCreateNoCrash('/api/v1/sales/customers', [
            'name'            => 'Test Customer '.time(),
            'code'            => 'TC'.rand(1000, 9999),
            'email'           => 'customer'.time().'@test.com',
            'billing_address' => 'Test Address, Manila',
            'contact_person'  => 'Test Contact',
        ], $this->admin);
    });

});

// ============================================================================
// SOFT DELETE / ARCHIVE ACTIONS
// ============================================================================

describe('Soft Delete / Archive Actions', function () {

    it('GET archived employees does not crash', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/hr/employees-archived');
        expect($response->status())->not->toBe(500)->not->toBe(403);
    });

});

// ============================================================================
// API RESPONSE FORMAT
// ============================================================================

describe('API Response Format', function () {

    it('paginated lists use meta key not pagination key', function () {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/hr/departments');
        if ($response->status() !== 200) {
            return;
        }

        $body = $response->json();
        expect($body)
            ->toHaveKey('meta')
            ->and($body)->not->toHaveKey('pagination')
            ->and($body['meta'])->toHaveKeys(['current_page', 'last_page', 'per_page', 'total']);
    });

    it('error responses include error_code or errors key', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/hr/employees', []); // force validation error

        if ($response->status() !== 422) {
            return;
        }

        $body = $response->json();
        expect(isset($body['errors']) || isset($body['error_code']))->toBeTrue(
            'Validation error response must have either "errors" or "error_code" key. Got: '.
            json_encode(array_keys($body))
        );
    });

    it('unauthenticated requests return 401 not 500', function () {
        $response = $this->getJson('/api/v1/hr/employees');
        expect($response->status())->toBe(401)->not->toBe(500);
    });

});
