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

    $this->customer = Customer::factory()->create(['is_active' => true]);
    $this->fiscalPeriod = \App\Domains\Accounting\Models\FiscalPeriod::first();

    $this->service = app(CustomerInvoiceService::class);
});

it('allows creating invoice with valid delivered receipt', function () {
    $deliveryReceipt = DeliveryReceipt::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'delivered',
    ]);

    $invoice = $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => 1,
        'revenue_account_id' => 2,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000.00,
        'vat_amount' => 120.00,
        'delivery_receipt_id' => $deliveryReceipt->id,
    ], 1);

    expect($invoice->delivery_receipt_id)->toBe($deliveryReceipt->id);
});

it('throws exception when delivery receipt not found', function () {
    expect(fn () => $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => 1,
        'revenue_account_id' => 2,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000.00,
        'vat_amount' => 120.00,
        'delivery_receipt_id' => 99999,
    ], 1))->toThrow(DomainException::class, 'Delivery receipt not found.');
});

it('throws exception when delivery receipt belongs to different customer', function () {
    $otherCustomer = Customer::factory()->create(['is_active' => true]);
    $deliveryReceipt = DeliveryReceipt::factory()->create([
        'customer_id' => $otherCustomer->id,
        'status' => 'delivered',
    ]);

    expect(fn () => $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => 1,
        'revenue_account_id' => 2,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000.00,
        'vat_amount' => 120.00,
        'delivery_receipt_id' => $deliveryReceipt->id,
    ], 1))->toThrow(DomainException::class, 'Delivery receipt does not belong to this customer.');
});

it('throws exception when delivery is not completed', function () {
    $deliveryReceipt = DeliveryReceipt::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'pending',
    ]);

    expect(fn () => $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => 1,
        'revenue_account_id' => 2,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000.00,
        'vat_amount' => 120.00,
        'delivery_receipt_id' => $deliveryReceipt->id,
    ], 1))->toThrow(DomainException::class, 'Delivery must be completed first.');
});

it('throws exception when delivery receipt is already invoiced', function () {
    $deliveryReceipt = DeliveryReceipt::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'delivered',
    ]);

    // Create first invoice
    $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => 1,
        'revenue_account_id' => 2,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000.00,
        'vat_amount' => 120.00,
        'delivery_receipt_id' => $deliveryReceipt->id,
    ], 1);

    // Try to create second invoice with same delivery receipt
    expect(fn () => $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => 1,
        'revenue_account_id' => 2,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 2000.00,
        'vat_amount' => 240.00,
        'delivery_receipt_id' => $deliveryReceipt->id,
    ], 1))->toThrow(DomainException::class, 'Delivery receipt is already linked to invoice');
});

it('allows creating invoice without delivery receipt for non-delivery sales', function () {
    $invoice = $this->service->create($this->customer, [
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'ar_account_id' => 1,
        'revenue_account_id' => 2,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 1000.00,
        'vat_amount' => 120.00,
        // No delivery_receipt_id
    ], 1);

    expect($invoice->delivery_receipt_id)->toBeNull();
});
