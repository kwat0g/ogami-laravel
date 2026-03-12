<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\AR\Models\CustomerInvoice;
use App\Models\User;
use App\Notifications\AR\InvoiceOverdueNotification;
use Illuminate\Console\Command;

/**
 * Checks for overdue AR invoices and sends notifications.
 * Designed to run daily via scheduler.
 */
final class CheckOverdueArInvoicesCommand extends Command
{
    protected $signature = 'ar:check-overdue {--days=0 : Minimum days overdue to notify}';
    protected $description = 'Send notifications for overdue customer invoices';

    public function handle(): int
    {
        $minDays = (int) $this->option('days');

        $overdue = CustomerInvoice::with('customer')
            ->where('status', 'approved')
            ->where('due_date', '<', now())
            ->whereColumn('total_amount', '>', \Illuminate\Support\Facades\DB::raw(
                "COALESCE((SELECT SUM(amount) FROM customer_payments WHERE customer_payments.customer_invoice_id = customer_invoices.id), 0)"
            ))
            ->get();

        $notified = 0;

        // Notify AR managers
        $arUsers = User::permission('ar.invoices.manage')->get();

        foreach ($overdue as $invoice) {
            $daysOverdue = (int) $invoice->due_date->diffInDays(now(), false);
            if ($daysOverdue < $minDays) {
                continue;
            }

            foreach ($arUsers as $user) {
                $user->notify(new InvoiceOverdueNotification($invoice, $daysOverdue));
            }
            $notified++;
        }

        $this->info("Notified about {$notified} overdue invoices to " . $arUsers->count() . " AR users.");

        return self::SUCCESS;
    }
}
