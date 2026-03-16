# 📧 Notification Wiring Implementation Plan (CRIT-001)

**Status:** Ready to Implement  
**Priority:** HIGH  
**Estimated Effort:** 2-3 hours

---

## 🎯 Scope

Complete the notification wiring for 3 scheduled jobs that currently only log:

| Job | File | Current State | Target |
|-----|------|---------------|--------|
| AP Daily Digest | `SendApDailyDigestJob.php` | Logs only | Email to Accounting Manager |
| AP Due Date Alert | `SendApDueDateAlertJob.php` | Logs only | Notifications for overdue/upcoming invoices |
| Stale JE Flagging | `FlagStaleJournalEntriesJob.php` | Logs only | Notify drafters of stale entries |

---

## 📋 Implementation Tasks

### Task 1: Create Notification Classes

#### 1.1 `ApDailyDigestNotification`
- **Type:** Database + Broadcast + Mail (optional)
- **Recipients:** Accounting Manager role
- **Content:** Summary of pending, approved, overdue, due-this-week AP invoices

#### 1.2 `ApDueDateAlertNotification`
- **Type:** Database + Broadcast
- **Recipients:** 
  - Overdue → Accounting Manager
  - Due Soon → Accounting Staff role
- **Content:** Invoice details, vendor, due date, balance due

#### 1.3 `JournalEntryStaleNotification`
- **Type:** Database + Broadcast
- **Recipients:** JE drafter (created_by user)
- **Content:** JE reference, days stale, action required

---

### Task 2: Update Jobs to Send Notifications

#### 2.1 `SendApDailyDigestJob.php`
- Find users with `accounting.manager` permission
- Send `ApDailyDigestNotification`
- Keep existing logging

#### 2.2 `SendApDueDateAlertJob.php`
- Overdue invoices → Notify accounting managers
- Due soon invoices → Notify accounting staff
- Send `ApDueDateAlertNotification` with context (overdue vs due-soon)

#### 2.3 `FlagStaleJournalEntriesJob.php`
- Track which JEs were flagged stale
- Notify each drafter about their stale entries
- Batch notifications (group by user)

---

### Task 3: Create Mailable (Optional)
- `ApDigestMail` - HTML email for daily digest
- Professional table layout with invoice summary

---

### Task 4: Testing
- Unit tests for each notification class
- Feature tests for job notification dispatching
- Verify notifications appear in database

---

## 🏗️ Technical Design

### Notification Pattern (following existing codebase)

```php
// Example: ApDailyDigestNotification
final class ApDailyDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly object $summary,
        private readonly string $date
    ) {
        $this->queue = 'notifications';
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast']; // Add 'mail' if needed
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ap.daily_digest',
            'title' => 'AP Daily Digest',
            'message' => sprintf(
                'AP Summary for %s: %d pending, %d approved, %d overdue, %d due this week. Outstanding: ₱%s',
                $this->date,
                $this->summary->pending_count,
                $this->summary->approved_count,
                $this->summary->overdue_count,
                $this->summary->due_this_week_count,
                number_format($this->summary->outstanding_balance_centavos / 100, 2)
            ),
            'action_url' => '/accounting/ap/invoices',
            'summary' => [
                'pending' => $this->summary->pending_count,
                'approved' => $this->summary->approved_count,
                'overdue' => $this->summary->overdue_count,
                'due_this_week' => $this->summary->due_this_week_count,
                'outstanding_balance' => $this->summary->outstanding_balance_centavos,
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
```

### Job Update Pattern

```php
// In SendApDailyDigestJob::handle()
$accountingManagers = User::permission('accounting.manager')->get();
$notification = new ApDailyDigestNotification($summary, $today);

foreach ($accountingManagers as $manager) {
    $manager->notify($notification);
}
```

---

## 📁 Files to Create/Modify

### New Files
```
app/Notifications/ApDailyDigestNotification.php
app/Notifications/ApDueDateAlertNotification.php
app/Notifications/JournalEntryStaleNotification.php
app/Mail/ApDigestMail.php (optional)
```

### Modified Files
```
app/Jobs/AP/SendApDailyDigestJob.php
app/Jobs/AP/SendApDueDateAlertJob.php
app/Jobs/Accounting/FlagStaleJournalEntriesJob.php
```

### Test Files
```
tests/Unit/Notifications/ApDailyDigestNotificationTest.php
tests/Unit/Notifications/ApDueDateAlertNotificationTest.php
tests/Unit/Notifications/JournalEntryStaleNotificationTest.php
tests/Feature/Jobs/SendApDailyDigestJobTest.php
tests/Feature/Jobs/SendApDueDateAlertJobTest.php
tests/Feature/Jobs/FlagStaleJournalEntriesJobTest.php
```

---

## ✅ Acceptance Criteria

- [ ] Accounting Managers receive daily AP digest notification
- [ ] Accounting Managers receive overdue invoice alerts
- [ ] Accounting Staff receive upcoming due date alerts
- [ ] JE drafters receive stale entry notifications
- [ ] All notifications stored in database
- [ ] All notifications broadcast for real-time updates
- [ ] Existing logging preserved
- [ ] Unit tests pass
- [ ] Feature tests pass

---

## 🚀 Next Steps

1. **Approve this plan** - Confirm scope and approach
2. **Implement notification classes** - Create 3 notification classes
3. **Update jobs** - Wire notifications into existing jobs
4. **Write tests** - Unit and feature tests
5. **Run verification** - Test notifications are dispatched correctly

Ready to proceed? 
