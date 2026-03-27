<?php

declare(strict_types=1);

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEntryLine;
use App\Domains\Budget\Models\AnnualBudget;
use App\Domains\Budget\Models\CostCenter;
use App\Domains\Budget\Services\BudgetVarianceService;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'budget', 'variance');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');

    $this->costCenter = CostCenter::create([
        'name' => 'Admin Department',
        'code' => 'ADMIN',
        'is_active' => true,
        'created_by_id' => $this->manager->id,
    ]);

    $this->account = ChartOfAccount::factory()->create([
        'code' => '5100',
        'name' => 'Office Supplies',
        'type' => 'expense',
    ]);

    $this->fiscalYear = (int) now()->format('Y');
});

it('returns variance report with correct utilization', function () {
    // Create an approved budget line: 100,000 PHP = 10,000,000 centavos
    AnnualBudget::create([
        'cost_center_id' => $this->costCenter->id,
        'fiscal_year' => $this->fiscalYear,
        'account_id' => $this->account->id,
        'budgeted_amount_centavos' => 10_000_000,
        'status' => 'approved',
        'created_by_id' => $this->manager->id,
    ]);

    $service = app(BudgetVarianceService::class);
    $report = $service->varianceReport(['fiscal_year' => $this->fiscalYear]);

    expect($report)->toHaveCount(1)
        ->and($report->first()['budgeted_centavos'])->toBe(10_000_000)
        ->and($report->first()['cost_center_name'])->toBe('Admin Department')
        ->and($report->first()['status'])->toBe('under_budget');
});

it('excludes draft and rejected budget lines', function () {
    AnnualBudget::create([
        'cost_center_id' => $this->costCenter->id,
        'fiscal_year' => $this->fiscalYear,
        'account_id' => $this->account->id,
        'budgeted_amount_centavos' => 5_000_000,
        'status' => 'draft',
        'created_by_id' => $this->manager->id,
    ]);

    $service = app(BudgetVarianceService::class);
    $report = $service->varianceReport(['fiscal_year' => $this->fiscalYear]);

    expect($report)->toBeEmpty();
});

it('aggregates variance by cost center', function () {
    // Two budget lines for the same cost center
    AnnualBudget::create([
        'cost_center_id' => $this->costCenter->id,
        'fiscal_year' => $this->fiscalYear,
        'account_id' => $this->account->id,
        'budgeted_amount_centavos' => 5_000_000,
        'status' => 'approved',
        'created_by_id' => $this->manager->id,
    ]);

    $account2 = ChartOfAccount::factory()->create([
        'code' => '5200',
        'name' => 'Travel Expenses',
        'type' => 'expense',
    ]);

    AnnualBudget::create([
        'cost_center_id' => $this->costCenter->id,
        'fiscal_year' => $this->fiscalYear,
        'account_id' => $account2->id,
        'budgeted_amount_centavos' => 3_000_000,
        'status' => 'approved',
        'created_by_id' => $this->manager->id,
    ]);

    $service = app(BudgetVarianceService::class);
    $summary = $service->varianceByCostCenter($this->fiscalYear);

    expect($summary)->toHaveCount(1)
        ->and($summary->first()['total_budgeted_centavos'])->toBe(8_000_000)
        ->and($summary->first()['line_count'])->toBe(2);
});

it('returns year-end forecast', function () {
    AnnualBudget::create([
        'cost_center_id' => $this->costCenter->id,
        'fiscal_year' => $this->fiscalYear,
        'account_id' => $this->account->id,
        'budgeted_amount_centavos' => 12_000_000,
        'status' => 'approved',
        'created_by_id' => $this->manager->id,
    ]);

    $service = app(BudgetVarianceService::class);
    $forecast = $service->yearEndForecast($this->fiscalYear);

    expect($forecast)->toHaveCount(1)
        ->and($forecast->first()['budgeted_centavos'])->toBe(12_000_000)
        ->and($forecast->first())->toHaveKeys([
            'projected_centavos',
            'projected_variance_centavos',
            'months_elapsed',
        ]);
});

// ── HTTP Endpoint Tests ────────────────────────────────────────────────────

it('GET /budget/variance returns JSON variance report', function () {
    AnnualBudget::create([
        'cost_center_id' => $this->costCenter->id,
        'fiscal_year' => $this->fiscalYear,
        'account_id' => $this->account->id,
        'budgeted_amount_centavos' => 10_000_000,
        'status' => 'approved',
        'created_by_id' => $this->manager->id,
    ]);

    $this->actingAs($this->manager)
        ->getJson("/api/v1/budget/variance?fiscal_year={$this->fiscalYear}")
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('GET /budget/variance requires fiscal_year param', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/budget/variance')
        ->assertUnprocessable();
});
