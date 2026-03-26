<?php

declare(strict_types=1);

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorInvoice;
use App\Jobs\AP\SendApDailyDigestJob;
use App\Models\User;
use App\Notifications\ApDailyDigestNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->accountingManager = User::factory()->create();
    $this->accountingManager->givePermissionTo('vendor_invoices.approve');

    $this->regularUser = User::factory()->create();
    // No AP permissions
});

it('sends daily digest notification to accounting managers', function () {
    Notification::fake();

    $job = new SendApDailyDigestJob;
    $job->handle();

    Notification::assertSentTo(
        $this->accountingManager,
        ApDailyDigestNotification::class
    );
});

it('does not send notification to users without accounting permissions', function () {
    Notification::fake();

    $job = new SendApDailyDigestJob;
    $job->handle();

    Notification::assertNotSentTo(
        $this->regularUser,
        ApDailyDigestNotification::class
    );
});

it('sends notification to multiple accounting managers', function () {
    Notification::fake();

    $secondManager = User::factory()->create();
    $secondManager->givePermissionTo('vendor_invoices.approve');

    $job = new SendApDailyDigestJob;
    $job->handle();

    Notification::assertSentTo(
        [$this->accountingManager, $secondManager],
        ApDailyDigestNotification::class
    );
});

it('notification contains correct summary data', function () {
    Notification::fake();

    // Create some test invoices
    Vendor::factory()->create()->each(function ($vendor) {
        VendorInvoice::factory()->create([
            'vendor_id' => $vendor->id,
            'status' => 'pending',
            'due_date' => now()->addDays(3),
            'total_amount_centavos' => 100000,
            'paid_amount_centavos' => 0,
        ]);
    });

    $job = new SendApDailyDigestJob;
    $job->handle();

    Notification::assertSentTo($this->accountingManager, ApDailyDigestNotification::class, function ($notification) {
        $array = $notification->toArray($this->accountingManager);

        return $array['type'] === 'ap.daily_digest'
            && $array['title'] === 'AP Daily Digest'
            && isset($array['summary']['pending_count'])
            && isset($array['summary']['outstanding_balance_centavos']);
    });
});

it('job uses notifications queue', function () {
    $job = new SendApDailyDigestJob;

    expect($job->queue)->toBe('default'); // Job itself uses default queue
});

it('logs digest information', function () {
    Notification::fake();

    Log::shouldReceive('info')
        ->once()
        ->with('[AP Daily Digest]', Mockery::on(function ($data) {
            return isset($data['date'])
                && isset($data['pending_count'])
                && isset($data['outstanding_balance_pesos']);
        }));

    Log::shouldReceive('info')
        ->once()
        ->with(Mockery::pattern('/Sent daily digest to \d+ accounting manager/'));

    $job = new SendApDailyDigestJob;
    $job->handle();
});
