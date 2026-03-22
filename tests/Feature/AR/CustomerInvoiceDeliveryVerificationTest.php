<?php

declare(strict_types=1);

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Services\CustomerInvoiceService;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Shared\Exceptions\DomainException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
    $this->seed(\Database\Seeders\FiscalPeriodSeeder::class);

    $this->user = \App\Models\User::factory()->create();
    $this->actingAs($this->user);

    $this->customer = Customer::factory()->create(['is_active' => true, 'created_by' => $this->user->id]);
    $this->fiscalPeriod = \App\Domains\Accounting\Models\FiscalPeriod::first();

    // Get real account IDs from database
    $this->arAccount = \App\Domains\Accounting\Models\ChartOfAccount::where('account_type', 'ASSET')->first();
    $this->revAccount = \App\Domains\Accounting\Models\ChartOfAccount::where('account_type', 'REVENUE')->first();

    $this->service = app(CustomerInvoiceService::class);
});

it('allows creating invoice with valid delivered receipt', function () {
    $user = \App\Models\User::factory()->create();
    $deliveryReceipt = DeliveryReceipt::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'delivered',
        'created_by_id' => $user->id,
    ]);

    $invoice = $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->revAccount->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000.00,
        'vat_amount' => 120.00,
        'delivery_receipt_id' => $deliveryReceipt->id,
    ], $this->user->id);

    expect($invoice->delivery_receipt_id)->toBe($deliveryReceipt->id);
});

it('throws exception when delivery receipt not found', function () {
    expect(fn () => $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->revAccount->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000.00,
        'vat_amount' => 120.00,
        'delivery_receipt_id' => 99999,
    ], $this->user->id))->toThrow(DomainException::class, 'Delivery receipt not found.');
});

it('throws exception when delivery receipt belongs to different customer', function () {
    $user = \App\Models\User::factory()->create();
    $otherCustomer = Customer::factory()->create(['is_active' => true, 'created_by' => $user->id]);
    $deliveryReceipt = DeliveryReceipt::factory()->create([
        'customer_id' => $otherCustomer->id,
        'status' => 'delivered',
        'created_by_id' => $user->id,
    ]);

    expect(fn () => $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->revAccount->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000.00,
        'vat_amount' => 120.00,
        'delivery_receipt_id' => $deliveryReceipt->id,
    ], $this->user->id))->toThrow(DomainException::class, 'Delivery receipt does not belong to this customer.');
});

it('throws exception when delivery is not completed', function () {
    $user = \App\Models\User::factory()->create();
    $deliveryReceipt = DeliveryReceipt::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'draft',
        'created_by_id' => $user->id,
    ]);

    expect(fn () => $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->revAccount->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000.00,
        'vat_amount' => 120.00,
        'delivery_receipt_id' => $deliveryReceipt->id,
    ], $this->user->id))->toThrow(DomainException::class, 'Delivery must be completed first.');
});

it('throws exception when delivery receipt is already invoiced', function () {
    $user = \App\Models\User::factory()->create();
    $deliveryReceipt = DeliveryReceipt::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'delivered',
        'created_by_id' => $user->id,
    ]);

    // Create first invoice
    $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->revAccount->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000.00,
        'vat_amount' => 120.00,
        'delivery_receipt_id' => $deliveryReceipt->id,
    ], $this->user->id);

    // Try to create second invoice with same delivery receipt
    expect(fn () => $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->revAccount->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 2000.00,
        'vat_amount' => 240.00,
        'delivery_receipt_id' => $deliveryReceipt->id,
    ], $this->user->id))->toThrow(DomainException::class, 'Delivery receipt is already linked to invoice');
});

it('allows creating invoice without delivery receipt for non-delivery sales', function () {
    $invoice = $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => $this->arAccount->id,
        'revenue_account_id' => $this->revAccount->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000.00,
        'vat_amount' => 120.00,
        // No delivery_receipt_id
    ], $this->user->id);

    expect($invoice->delivery_receipt_id)->toBeNull();
});
