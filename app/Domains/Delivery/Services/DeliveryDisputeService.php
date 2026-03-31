<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Services;

use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerCreditNote;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Services\CustomerCreditNoteService;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\CRM\Services\TicketService;
use App\Domains\Delivery\Models\DeliveryDispute;
use App\Domains\Delivery\Models\DeliveryDisputeItem;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Production\Models\DeliverySchedule;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * DeliveryDisputeService -- manages the delivery dispute lifecycle.
 *
 * Disputes are auto-created when a client reports damaged/missing items
 * during delivery acknowledgment. Staff resolves disputes with concrete
 * actions: replace items, issue credit, or accept partial delivery.
 */
final class DeliveryDisputeService implements ServiceContract
{
    /**
     * List disputes with filters.
     */
    public function index(array $filters = []): mixed
    {
        return DeliveryDispute::query()
            ->with(['customer', 'clientOrder', 'reportedBy', 'assignedTo', 'items.itemMaster'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(
                fn ($q2) => $q2->where('dispute_reference', 'ilike', "%{$v}%")
                    ->orWhereHas('customer', fn ($q3) => $q3->where('name', 'ilike', "%{$v}%"))
            ))
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Create a dispute from a client's delivery acknowledgment.
     *
     * @param  array<int, array{item_master_id: int, expected_qty: float, received_qty: float, condition: string, notes?: string}>  $items
     */
    public function createFromAcknowledgment(
        DeliverySchedule $schedule,
        array $items,
        User $reporter,
        ?string $clientNotes = null,
    ): DeliveryDispute {
        return DB::transaction(function () use ($schedule, $items, $reporter, $clientNotes): DeliveryDispute {
            $dispute = DeliveryDispute::create([
                'dispute_reference' => $this->generateReference(),
                'delivery_schedule_id' => $schedule->id,
                'client_order_id' => $schedule->client_order_id,
                'customer_id' => $schedule->customer_id,
                'delivery_receipt_id' => $schedule->deliveryReceipts()->latest()->value('id'),
                'reported_by_id' => $reporter->id,
                'status' => 'open',
                'client_notes' => $clientNotes,
            ]);

            foreach ($items as $item) {
                if ($item['condition'] === 'good' && (float) $item['received_qty'] >= (float) $item['expected_qty']) {
                    continue; // Skip items with no issues
                }
                DeliveryDisputeItem::create([
                    'delivery_dispute_id' => $dispute->id,
                    'item_master_id' => $item['item_master_id'],
                    'expected_qty' => $item['expected_qty'],
                    'received_qty' => $item['received_qty'],
                    'condition' => $item['condition'],
                    'notes' => $item['notes'] ?? null,
                    'photo_url' => $item['photo_url'] ?? null,
                ]);
            }

            // Auto-create CRM ticket for communication thread
            try {
                $ticketService = app(TicketService::class);
                $ticket = $ticketService->open([
                    'customer_id' => $schedule->customer_id,
                    'client_user_id' => $reporter->id,
                    'subject' => "Delivery Dispute - {$dispute->dispute_reference}",
                    'category' => 'delivery_dispute',
                    'priority' => 'high',
                    'description' => $this->buildDisputeDescription($dispute),
                ], $reporter);
                $dispute->update(['ticket_id' => $ticket->id]);
            } catch (\Throwable $e) {
                // Non-fatal: dispute created even if ticket creation fails
                \Illuminate\Support\Facades\Log::warning('[DeliveryDispute] Failed to auto-create ticket', [
                    'dispute_id' => $dispute->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $dispute->load('items.itemMaster', 'customer');
        });
    }

    /**
     * Assign a dispute to a staff member for investigation.
     */
    public function assign(DeliveryDispute $dispute, int $assigneeId): DeliveryDispute
    {
        if (in_array($dispute->status, ['resolved', 'closed'], true)) {
            throw new DomainException(
                'Cannot assign a resolved or closed dispute.',
                'DISPUTE_ALREADY_RESOLVED',
                422,
            );
        }

        $dispute->update([
            'assigned_to_id' => $assigneeId,
            'status' => $dispute->status === 'open' ? 'investigating' : $dispute->status,
        ]);

        return $dispute->fresh();
    }

    /**
     * Resolve a dispute with item-level resolution actions.
     *
     * @param  array<int, array{item_id: int, action: string, qty: float}>  $resolutions
     */
    public function resolve(
        DeliveryDispute $dispute,
        string $resolutionType,
        array $resolutions,
        User $resolver,
        ?string $notes = null,
    ): DeliveryDispute {
        if (in_array($dispute->status, ['resolved', 'closed'], true)) {
            throw new DomainException(
                'Dispute is already resolved.',
                'DISPUTE_ALREADY_RESOLVED',
                422,
            );
        }

        return DB::transaction(function () use ($dispute, $resolutionType, $resolutions, $resolver, $notes): DeliveryDispute {
            // Update per-item resolutions
            foreach ($resolutions as $res) {
                DeliveryDisputeItem::where('delivery_dispute_id', $dispute->id)
                    ->where('id', $res['item_id'])
                    ->update([
                        'resolution_action' => $res['action'],
                        'resolution_qty' => $res['qty'],
                    ]);
            }

            $updateData = [
                'status' => 'resolved',
                'resolution_type' => $resolutionType,
                'resolution_notes' => $notes,
                'resolved_by_id' => $resolver->id,
                'resolved_at' => now(),
            ];

            // Execute resolution action
            if ($resolutionType === 'credit_note') {
                $creditNote = $this->createCreditNote($dispute, $resolutions, $resolver);
                if ($creditNote) {
                    $updateData['credit_note_id'] = $creditNote->id;
                }
            }

            if (in_array($resolutionType, ['replace_items', 'full_replacement'], true)) {
                $replacementSchedule = $this->createReplacementSchedule($dispute, $resolutions, $resolver);
                if ($replacementSchedule) {
                    $updateData['replacement_schedule_id'] = $replacementSchedule->id;
                    // Stay pending until replacement is delivered
                    $updateData['status'] = 'pending_resolution';
                }
            }

            $dispute->update($updateData);

            // If resolved (not pending), transition client order to fulfilled
            if ($updateData['status'] === 'resolved') {
                $this->tryFulfillClientOrder($dispute);
            }

            return $dispute->fresh(['items.itemMaster', 'customer', 'creditNote', 'replacementSchedule']);
        });
    }

    /**
     * Close a resolved dispute.
     */
    public function close(DeliveryDispute $dispute): DeliveryDispute
    {
        if ($dispute->status !== 'resolved') {
            throw new DomainException(
                'Only resolved disputes can be closed.',
                'DISPUTE_NOT_RESOLVED',
                422,
            );
        }

        $dispute->update(['status' => 'closed']);

        return $dispute->fresh();
    }

    /**
     * Check if a client order has any open disputes preventing fulfillment.
     */
    public function hasOpenDisputes(int $clientOrderId): bool
    {
        return DeliveryDispute::where('client_order_id', $clientOrderId)
            ->whereIn('status', ['open', 'investigating', 'pending_resolution'])
            ->exists();
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function createCreditNote(
        DeliveryDispute $dispute,
        array $resolutions,
        User $actor,
    ): ?CustomerCreditNote {
        $customer = Customer::findOrFail($dispute->customer_id);

        // Calculate credit amount from disputed items marked for credit
        $creditItems = collect($resolutions)->filter(fn ($r) => $r['action'] === 'credit');
        if ($creditItems->isEmpty()) {
            return null;
        }

        // Find the invoice linked to this delivery
        $invoice = CustomerInvoice::where('delivery_receipt_id', $dispute->delivery_receipt_id)
            ->where('status', '!=', 'cancelled')
            ->first();

        // Calculate credit amount based on original item prices
        $totalCreditCentavos = 0;
        foreach ($creditItems as $ci) {
            $disputeItem = DeliveryDisputeItem::find($ci['item_id']);
            if (! $disputeItem) {
                continue;
            }
            // Look up the unit price from the invoice or client order
            $unitPriceCentavos = $this->getItemUnitPrice($dispute, $disputeItem->item_master_id);
            $totalCreditCentavos += (int) round($unitPriceCentavos * (float) $ci['qty']);
        }

        if ($totalCreditCentavos <= 0) {
            return null;
        }

        try {
            $creditNoteService = app(CustomerCreditNoteService::class);

            // Find AR account (use first available)
            $arAccountId = DB::table('chart_of_accounts')
                ->where('account_code', 'like', '1130%')
                ->value('id') ?? DB::table('chart_of_accounts')->where('is_header', false)->value('id');

            return $creditNoteService->create($customer, [
                'note_type' => 'credit',
                'customer_invoice_id' => $invoice?->id,
                'note_date' => now()->toDateString(),
                'amount_centavos' => $totalCreditCentavos,
                'reason' => "Delivery dispute {$dispute->dispute_reference}: credit for damaged/missing items",
                'ar_account_id' => $arAccountId,
            ], $actor);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[DeliveryDispute] Failed to create credit note', [
                'dispute_id' => $dispute->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function createReplacementSchedule(
        DeliveryDispute $dispute,
        array $resolutions,
        User $actor,
    ): ?DeliverySchedule {
        $replaceItems = collect($resolutions)->filter(fn ($r) => $r['action'] === 'replace');
        if ($replaceItems->isEmpty()) {
            return null;
        }

        try {
            // Create a new delivery schedule linked to the same client order
            $schedule = DeliverySchedule::create([
                'ulid' => (string) \Illuminate\Support\Str::ulid(),
                'cds_reference' => 'RPL-'.$dispute->dispute_reference,
                'customer_id' => $dispute->customer_id,
                'client_order_id' => $dispute->client_order_id,
                'status' => 'ready',
                'target_delivery_date' => now()->addDays(3)->toDateString(),
                'notes' => "Replacement delivery for dispute {$dispute->dispute_reference}",
            ]);

            return $schedule;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[DeliveryDispute] Failed to create replacement schedule', [
                'dispute_id' => $dispute->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function tryFulfillClientOrder(DeliveryDispute $dispute): void
    {
        if (! $dispute->client_order_id) {
            return;
        }

        // Only fulfill if no other open disputes for this order
        if ($this->hasOpenDisputes($dispute->client_order_id)) {
            return;
        }

        $order = ClientOrder::find($dispute->client_order_id);
        if ($order && $order->status === 'delivered') {
            $order->update(['status' => 'fulfilled']);
        }
    }

    private function getItemUnitPrice(DeliveryDispute $dispute, int $itemMasterId): int
    {
        // Try client order items first
        if ($dispute->client_order_id) {
            $price = DB::table('client_order_items')
                ->where('client_order_id', $dispute->client_order_id)
                ->where('item_master_id', $itemMasterId)
                ->value('unit_price_centavos');
            if ($price) {
                return (int) $price;
            }
        }

        // Fallback to item master standard price
        $price = DB::table('item_masters')
            ->where('id', $itemMasterId)
            ->value('standard_cost_centavos');

        return (int) ($price ?? 0);
    }

    private function buildDisputeDescription(DeliveryDispute $dispute): string
    {
        $dispute->loadMissing('items.itemMaster', 'customer', 'clientOrder');

        $lines = [
            "DELIVERY DISPUTE - {$dispute->dispute_reference}",
            "Customer: {$dispute->customer?->name}",
        ];

        if ($dispute->clientOrder) {
            $lines[] = "Client Order: {$dispute->clientOrder->order_reference}";
        }

        $lines[] = '';
        $lines[] = 'ITEMS WITH ISSUES:';

        foreach ($dispute->items as $item) {
            $name = $item->itemMaster?->name ?? "Item #{$item->item_master_id}";
            $line = "- {$name}: Expected {$item->expected_qty}, Received {$item->received_qty}, Condition: {$item->condition}";
            if ($item->notes) {
                $line .= "\n  Notes: \"{$item->notes}\"";
            }
            $lines[] = $line;
        }

        if ($dispute->client_notes) {
            $lines[] = '';
            $lines[] = 'CLIENT NOTES:';
            $lines[] = $dispute->client_notes;
        }

        return implode("\n", $lines);
    }

    private function generateReference(): string
    {
        $year = now()->format('Y');
        $seq = DeliveryDispute::whereYear('created_at', $year)->withTrashed()->count() + 1;

        return 'DDP-'.$year.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
