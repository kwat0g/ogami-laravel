<?php

declare(strict_types=1);

use App\Notifications\ApDailyDigestNotification;

beforeEach(function () {
    $this->summary = (object) [
        'pending_count' => 5,
        'approved_count' => 10,
        'overdue_count' => 3,
        'due_this_week_count' => 7,
        'outstanding_balance' => 250000.00, // ₱250,000.00
    ];
    $this->date = '2026-03-15';
});

it('returns correct via channels', function () {
    $notification = new ApDailyDigestNotification($this->summary, $this->date);
    $notifiable = new stdClass;

    expect($notification->via($notifiable))->toBe(['database', 'broadcast']);
});

it('formats notification array correctly', function () {
    $notification = new ApDailyDigestNotification($this->summary, $this->date);
    $notifiable = new stdClass;

    $array = $notification->toArray($notifiable);

    expect($array)->toHaveKeys([
        'type', 'title', 'message', 'action_url', 'summary',
    ])
        ->and($array['type'])->toBe('ap.daily_digest')
        ->and($array['title'])->toBe('AP Daily Digest')
        ->and($array['action_url'])->toBe('/accounting/ap/invoices')
        ->and($array['summary']['pending_count'])->toBe(5)
        ->and($array['summary']['approved_count'])->toBe(10)
        ->and($array['summary']['overdue_count'])->toBe(3)
        ->and($array['summary']['due_this_week_count'])->toBe(7)
        ->and($array['summary']['outstanding_balance'])->toBe(250000.0);
});

it('formats message with correct values', function () {
    $notification = new ApDailyDigestNotification($this->summary, $this->date);
    $notifiable = new stdClass;

    $array = $notification->toArray($notifiable);

    expect($array['message'])->toContain('2026-03-15')
        ->and($array['message'])->toContain('5 pending')
        ->and($array['message'])->toContain('10 approved')
        ->and($array['message'])->toContain('3 overdue')
        ->and($array['message'])->toContain('7 due this week')
        ->and($array['message'])->toContain('₱250,000.00');
});

it('handles zero values correctly', function () {
    $emptySummary = (object) [
        'pending_count' => 0,
        'approved_count' => 0,
        'overdue_count' => 0,
        'due_this_week_count' => 0,
        'outstanding_balance' => 0.0,
    ];

    $notification = new ApDailyDigestNotification($emptySummary, $this->date);
    $notifiable = new stdClass;

    $array = $notification->toArray($notifiable);

    expect($array['summary']['pending_count'])->toBe(0)
        ->and($array['summary']['outstanding_balance'])->toBe(0.0);
});

it('uses notifications queue', function () {
    $notification = new ApDailyDigestNotification($this->summary, $this->date);

    expect($notification->queue)->toBe('notifications');
});
