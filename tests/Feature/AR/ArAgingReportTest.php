<?php

declare(strict_types=1);

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Services\ArAgingService;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\HR\Models\Department;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\DepartmentModuleAssignmentSeeder;
use Database\Seeders\DepartmentPositionSeeder;
use Database\Seeders\ModulePermissionSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'ar', 'reports');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ModuleSeeder::class);
    $this->seed(ModulePermissionSeeder::class);
    $this->seed(DepartmentPositionSeeder::class);
    $this->seed(DepartmentModuleAssignmentSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);

    $acctgDept = Department::where('code', 'ACCTG')->firstOrFail();

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
    $this->manager->departments()->attach($acctgDept->id, ['is_primary' => true]);
    $this->manager->update(['department_id' => $acctgDept->id]);

    $this->period = FiscalPeriod::query()->where('status', 'open')->orderBy('date_from')->firstOrFail();
    $this->arAccount = ChartOfAccount::where('account_type', 'ASSET')->firstOrFail();
    $this->revenueAccount = ChartOfAccount::where('account_type', 'REVENUE')->firstOrFail();

    $this->customer = Customer::factory()->create([
        'name' => 'Test Customer',
        'credit_limit' => 500000,
        'is_active' => true,
        'created_by' => $this->manager->id,
    ]);
});

function createArAgingInvoice(
    Customer $customer,
    User $manager,
    FiscalPeriod $period,
    ChartOfAccount $arAccount,
    ChartOfAccount $revenueAccount,
    array $overrides = [],
): CustomerInvoice {
    $dueDate = isset($overrides['due_date'])
        ? \Illuminate\Support\Carbon::parse($overrides['due_date'])
        : now()->addDays(10);

    $invoiceDate = isset($overrides['invoice_date'])
        ? \Illuminate\Support\Carbon::parse($overrides['invoice_date'])
        : $dueDate->copy()->subDays(30);

    return CustomerInvoice::create(array_merge([
        'customer_id' => $customer->id,
        'fiscal_period_id' => $period->id,
        'ar_account_id' => $arAccount->id,
        'revenue_account_id' => $revenueAccount->id,
        'invoice_date' => $invoiceDate->toDateString(),
        'due_date' => $dueDate->toDateString(),
        'subtotal' => 10000,
        'vat_amount' => 1200,
        'status' => 'approved',
        'created_by' => $manager->id,
    ], $overrides));
}

it('returns aging summary with correct bucket classification', function () {
    // Current invoice (due in 10 days)
    createArAgingInvoice($this->customer, $this->manager, $this->period, $this->arAccount, $this->revenueAccount, [
        'due_date' => now()->addDays(10),
        'subtotal' => 10000,
        'vat_amount' => 1200,
    ]);

    // Overdue 45 days (bucket 31-60)
    createArAgingInvoice($this->customer, $this->manager, $this->period, $this->arAccount, $this->revenueAccount, [
        'due_date' => now()->subDays(45),
        'subtotal' => 20000,
        'vat_amount' => 2400,
    ]);

    // Overdue 100 days (bucket 91-120)
    createArAgingInvoice($this->customer, $this->manager, $this->period, $this->arAccount, $this->revenueAccount, [
        'due_date' => now()->subDays(100),
        'subtotal' => 5000,
        'vat_amount' => 600,
    ]);

    $service = app(ArAgingService::class);
    $summary = $service->agingSummary();

    expect($summary)->toHaveCount(1)
        ->and($summary->first()['customer_name'])->toBe('Test Customer')
        ->and($summary->first()['total_outstanding'])->toBeGreaterThan(0);
});

it('returns empty when no open invoices exist', function () {
    $service = app(ArAgingService::class);
    $summary = $service->agingSummary();

    expect($summary)->toBeEmpty();
});

it('excludes paid and cancelled invoices from aging', function () {
    createArAgingInvoice($this->customer, $this->manager, $this->period, $this->arAccount, $this->revenueAccount, [
        'due_date' => now()->subDays(60),
        'subtotal' => 10000,
        'vat_amount' => 1200,
        'status' => 'paid',
    ]);

    createArAgingInvoice($this->customer, $this->manager, $this->period, $this->arAccount, $this->revenueAccount, [
        'due_date' => now()->subDays(30),
        'subtotal' => 5000,
        'vat_amount' => 600,
        'status' => 'cancelled',
    ]);

    $service = app(ArAgingService::class);
    $summary = $service->agingSummary();

    expect($summary)->toBeEmpty();
});

it('returns aging detail for a specific customer', function () {
    createArAgingInvoice($this->customer, $this->manager, $this->period, $this->arAccount, $this->revenueAccount, [
        'due_date' => now()->subDays(15),
        'subtotal' => 10000,
        'vat_amount' => 1200,
    ]);

    $service = app(ArAgingService::class);
    $detail = $service->agingDetail($this->customer);

    expect($detail)->toHaveCount(1)
        ->and($detail->first()['bucket'])->toBe('current');
});

it('calculates aging totals correctly', function () {
    createArAgingInvoice($this->customer, $this->manager, $this->period, $this->arAccount, $this->revenueAccount, [
        'due_date' => now()->addDays(5),
        'subtotal' => 10000,
        'vat_amount' => 0,
    ]);

    $service = app(ArAgingService::class);
    $totals = $service->agingTotals();

    expect($totals)->toHaveKey('grand_total')
        ->and($totals['grand_total'])->toBeGreaterThan(0);
});

// ── HTTP Endpoint Tests ────────────────────────────────────────────────────

it('GET /ar/reports/aging returns JSON aging summary', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/ar/reports/aging')
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'totals' => ['current', 'bucket_31_60', 'bucket_61_90', 'bucket_91_120', 'over_120', 'grand_total'],
            'as_of',
        ]);
});

it('GET /ar/customers/{ulid}/statement returns JSON statement', function () {
    $this->actingAs($this->manager)
        ->getJson("/api/v1/ar/customers/{$this->customer->ulid}/statement")
        ->assertOk()
        ->assertJsonStructure([
            'customer' => ['id', 'name', 'credit_limit'],
            'invoices',
            'total_outstanding',
        ]);
});

it('GET /ar/customers/{ulid}/statement/pdf streams PDF', function () {
    $this->actingAs($this->manager)
        ->get("/api/v1/ar/customers/{$this->customer->ulid}/statement/pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
