<?php

declare(strict_types=1);

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorInvoice;
use App\Jobs\AP\SendApDueDateAlertJob;
use App\Models\User;
use App\Notifications\ApDueDateAlertNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    $this->vendor = Vendor::factory()->create();

    $this->accountingManager = User::factory()->create();
    $this->accountingManager->givePermissionTo('vendor_invoices.approve');

    $this->accountingStaff = User::factory()->create();
    $this->accountingStaff->givePermissionTo('vendor_invoices.view');
});

it('sends overdue alerts to accounting managers', function () {
    Notification::fake();

    // Create overdue invoice
    VendorInvoice::factory()->create([
        'vendor_id' => $this->vendor->id,
        'status' => 'pending',
        'due_date' => now()->subDays(5),
        'balance_due' => 50000,
    ]);

    $job = new SendApDueDateAlertJob();
    $job->handle();

    Notification::assertSentTo(
        $this->accountingManager,
        ApDueDateAlertNotification::class,
        function ($notification) {
            return $notification->toArray($this->accountingManager)['alert_type'] === 'overdue';
        }
    );
});

it('sends due-soon alerts to accounting staff', function () {
    Notification::fake();

    // Create invoice due soon
    VendorInvoice::factory()->create([
        'vendor_id' => $this->vendor->id,
        'status' => 'pending',
        'due_date' => now()->addDays(3),
        'balance_due' => 30000,
    ]);

    $job = new SendApDueDateAlertJob();
    $job->handle();

    Notification::assertSentTo(
        $this->accountingStaff,
        ApDueDateAlertNotification::class,
        function ($notification) {
            return $notification->toArray($this->accountingStaff)['alert_type'] === 'due_soon';
        }
    );
});

it('does not send alerts when no invoices match criteria', function () {
    Notification::fake();

    // Create invoice with future due date (outside alert window)
    VendorInvoice::factory()->create([
        'vendor_id' => $this->vendor->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
        'balance_due' => 50000,
    ]);

    $job = new SendApDueDateAlertJob();
    $job->handle();

    Notification::assertNothingSent();
});

it('includes correct invoice details in notification', function () {
    Notification::fake();

    $invoice = VendorInvoice::factory()->create([
        'vendor_id' => $this->vendor->id,
        'invoice_no' => 'INV-OVERDUE-001',
        'status' => 'pending',
        'due_date' => now()->subDays(10),
        'balance_due' => 75000,
    ]);

    $job = new SendApDueDateAlertJob();
    $job->handle();

    Notification::assertSentTo($this->accountingManager, ApDueDateAlertNotification::class, function ($notification) use ($invoice) {
        $array = $notification->toArray($this->accountingManager);

        return $array['invoice_no'] === 'INV-OVERDUE-001'
            && $array['vendor_name'] === $this->vendor->name
            && $array['balance_due'] === 75000.0
            && $array['days'] === 10;
    });
});

it('sends multiple notifications for multiple invoices', function () {
    Notification::fake();

    // Create multiple overdue invoices
    VendorInvoice::factory()->count(3)->create([
        'vendor_id' => $this->vendor->id,
        'status' => 'pending',
        'due_date' => now()->subDays(5),
        'balance_due' => 10000,
    ]);

    $job = new SendApDueDateAlertJob();
    $job->handle();

    Notification::assertSentToTimes($this->accountingManager, ApDueDateAlertNotification::class, 3);
});

it('loads alert days from system settings', function () {
    // Set custom alert days
    \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(
        ['key' => 'ap.due_date_alert_days'],
        ['value' => json_encode(14), 'created_at' => now(), 'updated_at' => now()]
    );

    Notification::fake();

    // Create invoice due in 10 days (within 14 day window)
    VendorInvoice::factory()->create([
        'vendor_id' => $this->vendor->id,
        'status' => 'pending',
        'due_date' => now()->addDays(10),
        'balance_due' => 50000,
    ]);

    $job = new SendApDueDateAlertJob();
    $job->handle();

    Notification::assertSentTo($this->accountingStaff, ApDueDateAlertNotification::class);
});
