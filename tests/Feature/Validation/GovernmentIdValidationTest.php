<?php

declare(strict_types=1);

use App\Domains\AP\Models\Vendor;
use App\Domains\HR\Models\Employee;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('employees.create');
    $this->user->givePermissionTo('employees.update');
    $this->actingAs($this->user);
});

// ── SSS Format Validation Tests ────────────────────────────────────────────

describe('SSS Number Format Validation', function () {
    it('accepts valid SSS format XX-XXXXXXX-X', function () {
        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'employment_type' => 'regular',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 250000,
            'date_hired' => now()->toDateString(),
            'sss_no' => '12-3456789-0',
        ]);

        $response->assertCreated();
        // EmployeeResource returns presence flag, not actual value (security)
        $response->assertJsonPath('data.has_sss_no', true);
    });

    it('rejects invalid SSS format', function () {
        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'employment_type' => 'regular',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 250000,
            'date_hired' => now()->toDateString(),
            'sss_no' => '123-456789-0', // Wrong format
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['sss_no']);
    });

    it('rejects SSS with wrong digit count', function () {
        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'employment_type' => 'regular',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 250000,
            'date_hired' => now()->toDateString(),
            'sss_no' => '12-345678-0', // Only 9 digits
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['sss_no']);
    });
});

// ── TIN Format Validation Tests ────────────────────────────────────────────

describe('TIN Format Validation', function () {
    it('accepts valid TIN format XXX-XXX-XXX-XXX', function () {
        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'employment_type' => 'regular',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 250000,
            'date_hired' => now()->toDateString(),
            'tin' => '123-456-789-000',
        ]);

        $response->assertCreated();
        // EmployeeResource returns presence flag, not actual value (security)
        $response->assertJsonPath('data.has_tin', true);
    });

    it('rejects invalid TIN format', function () {
        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'employment_type' => 'regular',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 250000,
            'date_hired' => now()->toDateString(),
            'tin' => '12-3456-789-000', // Wrong format
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['tin']);
    });

    it('rejects TIN with wrong digit count', function () {
        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'employment_type' => 'regular',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 250000,
            'date_hired' => now()->toDateString(),
            'tin' => '123-456-789-00', // Only 11 digits
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['tin']);
    });
});

// ── PhilHealth Format Validation Tests ─────────────────────────────────────

describe('PhilHealth Number Format Validation', function () {
    it('accepts valid PhilHealth format XX-XXXXXXXXX-X', function () {
        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'employment_type' => 'regular',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 250000,
            'date_hired' => now()->toDateString(),
            'philhealth_no' => '12-345678901-2',
        ]);

        $response->assertCreated();
        // EmployeeResource returns presence flag, not actual value (security)
        $response->assertJsonPath('data.has_philhealth_no', true);
    });

    it('rejects invalid PhilHealth format', function () {
        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'employment_type' => 'regular',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 250000,
            'date_hired' => now()->toDateString(),
            'philhealth_no' => '123-45678901-2', // Wrong format
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['philhealth_no']);
    });
});

// ── Pag-IBIG Format Validation Tests ───────────────────────────────────────

describe('Pag-IBIG Number Format Validation', function () {
    it('accepts valid Pag-IBIG format XXXX-XXXX-XXXX', function () {
        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'employment_type' => 'regular',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 250000,
            'date_hired' => now()->toDateString(),
            'pagibig_no' => '1234-5678-9012',
        ]);

        $response->assertCreated();
        // EmployeeResource returns presence flag, not actual value (security)
        $response->assertJsonPath('data.has_pagibig_no', true);
    });

    it('rejects invalid Pag-IBIG format', function () {
        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'employment_type' => 'regular',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 250000,
            'date_hired' => now()->toDateString(),
            'pagibig_no' => '123-4567-8901', // Wrong format
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['pagibig_no']);
    });
});

// ── Uniqueness Validation Tests ────────────────────────────────────────────

describe('Government ID Uniqueness Validation', function () {
    it('prevents duplicate SSS numbers', function () {
        // Create employee and set SSS via setter method (not in factory create to avoid column error)
        $employee = Employee::factory()->create([
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
        ]);
        $employee->setSssNo('12-3456789-0');
        $employee->save();

        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'date_of_birth' => '1995-01-01',
            'gender' => 'female',
            'employment_type' => 'regular',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 250000,
            'date_hired' => now()->toDateString(),
            'sss_no' => '12-3456789-0', // Same SSS
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['sss_no']);
    });

    it('prevents duplicate TIN numbers', function () {
        // Create employee and set TIN via setter method (not in factory create to avoid column error)
        $employee = Employee::factory()->create([
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
        ]);
        $employee->setTin('123-456-789-000');
        $employee->save();

        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'date_of_birth' => '1995-01-01',
            'gender' => 'female',
            'employment_type' => 'regular',
            'pay_basis' => 'monthly',
            'basic_monthly_rate' => 250000,
            'date_hired' => now()->toDateString(),
            'tin' => '123-456-789-000', // Same TIN
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['tin']);
    });
});

// ── Vendor TIN Validation Tests ────────────────────────────────────────────

describe('Vendor TIN Format Validation', function () {
    beforeEach(function () {
        $this->user->givePermissionTo('vendors.manage');
    });

    it('accepts valid TIN format for vendor', function () {
        $response = $this->postJson('/api/v1/accounting/vendors', [
            'name' => 'Acme Supplies',
            'tin' => '123-456-789-000',
            'is_ewt_subject' => false,
        ]);

        $response->assertCreated();
    });

    it('rejects invalid TIN format for vendor', function () {
        $response = $this->postJson('/api/v1/accounting/vendors', [
            'name' => 'Acme Supplies',
            'tin' => '12-3456-789-000', // Wrong format
            'is_ewt_subject' => false,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['tin']);
    });

    it('prevents duplicate TIN for vendors', function () {
        // Create vendor directly since no factory exists
        $vendor = Vendor::create([
            'name' => 'First Vendor',
            'tin' => '123-456-789-000',
            'is_ewt_subject' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/accounting/vendors', [
            'name' => 'Another Vendor',
            'tin' => '123-456-789-000', // Same TIN
            'is_ewt_subject' => false,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['tin']);
    });
});

// ── Customer TIN Validation Tests ──────────────────────────────────────────

describe('Customer TIN Format Validation', function () {
    beforeEach(function () {
        $this->user->givePermissionTo('customers.manage');
    });

    it('accepts valid TIN format for customer', function () {
        $response = $this->postJson('/api/v1/ar/customers', [
            'name' => 'ABC Corporation',
            'tin' => '123-456-789-000',
        ]);

        $response->assertCreated();
    });

    it('rejects invalid TIN format for customer', function () {
        $response = $this->postJson('/api/v1/ar/customers', [
            'name' => 'ABC Corporation',
            'tin' => '12-3456-789-000', // Wrong format
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['tin']);
    });
});

// ── Purchase Order Date Validation Tests ───────────────────────────────────
// Note: Manual PO creation is disabled (POs are auto-created on PR approval).
// We test that the validation rules exist in the FormRequest.

describe('Purchase Order Date Validation Rules', function () {
    it('has po_date validation requiring it to be before or equal to delivery_date', function () {
        $request = new StorePurchaseOrderRequest;
        $rules = $request->rules();

        // Verify the validation rule exists
        expect($rules)->toHaveKey('po_date');
        expect($rules['po_date'])->toContain('before_or_equal:delivery_date');
        expect($rules)->toHaveKey('delivery_date');
        expect($rules['delivery_date'])->toContain('after_or_equal:today');
    });
});
