<?php

declare(strict_types=1);

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Jobs\Accounting\FlagStaleJournalEntriesJob;
use App\Models\User;
use App\Notifications\JournalEntryStaleNotification;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FiscalPeriodSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FiscalPeriodSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);

    $this->drafter = User::factory()->create();

    $this->fiscalPeriod = FiscalPeriod::first();
});

it('flags stale draft journal entries', function () {
    // Create a stale draft JE (40 days old)
    $staleEntry = JournalEntry::factory()->create([
        'status' => 'draft',
        'created_by' => $this->drafter->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    $job = new FlagStaleJournalEntriesJob;
    $job->handle();

    $staleEntry->refresh();
    expect($staleEntry->status)->toBe('stale');
});

it('does not flag recent draft entries', function () {
    // Create a recent draft JE (5 days old)
    $recentEntry = JournalEntry::factory()->create([
        'status' => 'draft',
        'created_by' => $this->drafter->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    $job = new FlagStaleJournalEntriesJob;
    $job->handle();

    $recentEntry->refresh();
    expect($recentEntry->status)->toBe('draft');
});

it('notifies drafter when their entry is flagged stale', function () {
    Notification::fake();

    JournalEntry::factory()->create([
        'status' => 'draft',
        'created_by' => $this->drafter->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    $job = new FlagStaleJournalEntriesJob;
    $job->handle();

    Notification::assertSentTo(
        $this->drafter,
        JournalEntryStaleNotification::class
    );
});

it('does not notify when no entries are flagged', function () {
    Notification::fake();

    // Only create recent entries
    JournalEntry::factory()->create([
        'status' => 'draft',
        'created_by' => $this->drafter->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    $job = new FlagStaleJournalEntriesJob;
    $job->handle();

    Notification::assertNothingSent();
});

it('groups notifications by drafter', function () {
    Notification::fake();

    $secondDrafter = User::factory()->create();

    // Create multiple stale entries for first drafter
    JournalEntry::factory()->count(3)->create([
        'status' => 'draft',
        'created_by' => $this->drafter->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    // Create one stale entry for second drafter
    JournalEntry::factory()->create([
        'status' => 'draft',
        'created_by' => $secondDrafter->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    $job = new FlagStaleJournalEntriesJob;
    $job->handle();

    Notification::assertSentToTimes($this->drafter, JournalEntryStaleNotification::class, 3);
    Notification::assertSentToTimes($secondDrafter, JournalEntryStaleNotification::class, 1);
});

it('loads thresholds from system settings', function () {
    // Set custom stale days
    DB::table('system_settings')->updateOrInsert(
        ['key' => 'accounting.stale_draft_days'],
        ['value' => json_encode(15), 'created_at' => now(), 'updated_at' => now()]
    );

    $entry = JournalEntry::factory()->create([
        'status' => 'draft',
        'created_by' => $this->drafter->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'created_at' => now()->subDays(20),
        'updated_at' => now()->subDays(20),
    ]);

    $job = new FlagStaleJournalEntriesJob;
    $job->handle();

    $entry->refresh();
    expect($entry->status)->toBe('stale');
});

it('auto-cancels entries after extended inactivity', function () {
    // Set cancel days
    DB::table('system_settings')->updateOrInsert(
        ['key' => 'accounting.je_cancel_days'],
        ['value' => json_encode(60), 'created_at' => now(), 'updated_at' => now()]
    );

    $staleEntry = JournalEntry::factory()->create([
        'status' => 'stale',
        'created_by' => $this->drafter->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'created_at' => now()->subDays(70),
        'updated_at' => now()->subDays(70),
    ]);

    $job = new FlagStaleJournalEntriesJob;
    $job->handle();

    $staleEntry->refresh();
    expect($staleEntry->status)->toBe('cancelled');
});

it('logs flagging activity', function () {
    Notification::fake();

    JournalEntry::factory()->create([
        'status' => 'draft',
        'created_by' => $this->drafter->id,
        'fiscal_period_id' => $this->fiscalPeriod->id,
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with(Mockery::pattern('/Flagged \d+ draft JE\(s\) as stale/'));

    Log::shouldReceive('info')
        ->once()
        ->with(Mockery::pattern('/Sent stale notifications to \d+ drafter/'));

    $job = new FlagStaleJournalEntriesJob;
    $job->handle();
});
