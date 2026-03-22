<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\CRM\Models\ClientOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * CRM-SLA-001: Flag stale client order negotiations that have exceeded the SLA deadline.
 *
 * An order is considered stale when:
 *  - Status is 'negotiating' or 'client_responded' (active negotiation)
 *  - sla_deadline is set AND has passed (preferred)
 *  - OR last_negotiation_at is older than 7 days (fallback when sla_deadline is null)
 *
 * Stale orders are flagged by adding an internal note. Sales team should review and
 * resolve (reject or extend) manually. The command does NOT auto-reject to avoid
 * accidentally closing orders due to misconfiguration.
 */
final class ExpireStaleNegotiations extends Command
{
    protected $signature = 'crm:expire-stale-negotiations
                            {--dry-run : List stale orders without modifying them}';

    protected $description = 'Flag client order negotiations that have exceeded their SLA deadline';

    public function handle(): int
    {
        $now = now();
        $staleDays = 7;

        $query = ClientOrder::whereIn('status', [
            ClientOrder::STATUS_NEGOTIATING,
            ClientOrder::STATUS_CLIENT_RESPONDED,
        ])->where(function ($q) use ($now, $staleDays): void {
            $q->where(function ($inner) use ($now): void {
                // Primary: SLA deadline explicitly set and passed
                $inner->whereNotNull('sla_deadline')->where('sla_deadline', '<', $now);
            })->orWhere(function ($inner) use ($now, $staleDays): void {
                // Fallback: no SLA deadline, but no activity for 7+ days
                $inner->whereNull('sla_deadline')
                    ->where('last_negotiation_at', '<', $now->copy()->subDays($staleDays));
            });
        });

        $staleOrders = $query->get();

        if ($staleOrders->isEmpty()) {
            $this->info('No stale negotiations found.');

            return self::SUCCESS;
        }

        $this->info("Found {$staleOrders->count()} stale negotiation(s).");

        foreach ($staleOrders as $order) {
            $this->line("  [{$order->order_reference}] status={$order->status} last_activity={$order->last_negotiation_at}");

            if (! $this->option('dry-run')) {
                $staleSince = $order->sla_deadline
                    ? "SLA deadline was {$order->sla_deadline->format('Y-m-d')}"
                    : "No activity since {$order->last_negotiation_at?->format('Y-m-d')} ({$staleDays}+ days)";

                $order->update([
                    'internal_notes' => ($order->internal_notes ? $order->internal_notes."\n" : '')
                        ."[AUTO-FLAG] Stale negotiation — {$staleSince}. Requires sales review.",
                ]);

                Log::info('[CRM] Stale negotiation flagged', [
                    'order_reference' => $order->order_reference,
                    'status' => $order->status,
                    'last_negotiation_at' => $order->last_negotiation_at,
                    'sla_deadline' => $order->sla_deadline,
                ]);
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry-run mode — no changes made.');
        } else {
            $this->info("Flagged {$staleOrders->count()} stale negotiation(s).");
        }

        return self::SUCCESS;
    }
}
