<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\CRM\Models\Ticket;
use Illuminate\Console\Command;

/**
 * Scan open/in-progress tickets and mark those that have breached their SLA
 * deadline by setting sla_breached_at.
 *
 * Schedule: every 15 minutes in Console kernel.
 */
final class MarkSlaBreaches extends Command
{
    protected $signature = 'crm:mark-sla-breaches';

    protected $description = 'Mark CRM tickets that have breached their SLA deadline';

    public function handle(): int
    {
        $breached = Ticket::whereIn('status', ['open', 'in_progress', 'pending_client'])
            ->whereNotNull('sla_due_at')
            ->whereNull('sla_breached_at')
            ->where('sla_due_at', '<', now())
            ->get();

        if ($breached->isEmpty()) {
            $this->info('No SLA breaches detected.');

            return self::SUCCESS;
        }

        $now = now();
        foreach ($breached as $ticket) {
            $ticket->update(['sla_breached_at' => $now]);
        }

        $this->info("Marked {$breached->count()} ticket(s) as SLA breached.");

        return self::SUCCESS;
    }
}
