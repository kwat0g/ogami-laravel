<?php

declare(strict_types=1);

use App\Domains\AP\Models\Vendor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
});

function createVendor(array $overrides = []): Vendor
{
    return Vendor::create(array_merge([
        'name'                 => 'Test Vendor ' . uniqid(),
        'is_active'            => true,
        'is_ewt_subject'       => false,
        'accreditation_status' => 'pending',
        'created_by'           => 1,
    ], $overrides));
}

// ── Accredit ──────────────────────────────────────────────────────────────────

test('accounting officer cannot accredit a vendor', function () {
    $officer = User::factory()->create();
    $officer->assignRole('officer');

    $vendor = createVendor(['accreditation_status' => 'pending']);

    $this->actingAs($officer)
        ->patchJson("/api/v1/accounting/vendors/{$vendor->id}/accredit")
        ->assertForbidden();
});

test('staff cannot accredit a vendor', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $vendor = createVendor(['accreditation_status' => 'pending']);

    $this->actingAs($staff)
        ->patchJson("/api/v1/accounting/vendors/{$vendor->id}/accredit")
        ->assertForbidden();
});

test('purchasing officer can accredit a vendor', function () {
    $purchOfficer = User::factory()->create();
    $purchOfficer->assignRole('purchasing_officer');

    $vendor = createVendor(['accreditation_status' => 'pending']);

    $this->actingAs($purchOfficer)
        ->patchJson("/api/v1/accounting/vendors/{$vendor->id}/accredit")
        ->assertOk()
        ->assertJsonPath('data.accreditation_status', 'accredited');
});

// ── Suspend + auto-lock portal account ───────────────────────────────────────

test('accounting officer cannot suspend a vendor', function () {
    $officer = User::factory()->create();
    $officer->assignRole('officer');

    $vendor = createVendor(['accreditation_status' => 'accredited']);

    $this->actingAs($officer)
        ->patchJson("/api/v1/accounting/vendors/{$vendor->id}/suspend", [
            'reason' => 'Failed audit checks',
        ])
        ->assertForbidden();
});

test('suspending a vendor locks any linked portal user account', function () {
    $purchOfficer = User::factory()->create();
    $purchOfficer->assignRole('purchasing_officer');

    $vendor = createVendor(['accreditation_status' => 'accredited']);

    // Create a linked vendor portal user
    $portalUser = User::factory()->create(['vendor_id' => $vendor->id]);
    $portalUser->assignRole('vendor');

    expect($portalUser->fresh()->isLocked())->toBeFalse();

    $this->actingAs($purchOfficer)
        ->patchJson("/api/v1/accounting/vendors/{$vendor->id}/suspend", [
            'reason' => 'Failed audit checks',
        ])
        ->assertOk()
        ->assertJsonPath('data.accreditation_status', 'suspended');

    expect($portalUser->fresh()->isLocked())->toBeTrue();
});

test('suspending a vendor with no portal account does not error', function () {
    $purchOfficer = User::factory()->create();
    $purchOfficer->assignRole('purchasing_officer');

    $vendor = createVendor(['accreditation_status' => 'accredited']);

    $this->actingAs($purchOfficer)
        ->patchJson("/api/v1/accounting/vendors/{$vendor->id}/suspend", [
            'reason' => 'No portal account exists',
        ])
        ->assertOk()
        ->assertJsonPath('data.accreditation_status', 'suspended');
});
