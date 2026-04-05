<?php

declare(strict_types=1);

namespace App\Domains\Delivery\Services;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerCreditNote;
use App\Domains\AR\Models\CustomerInvoice;
use App\Domains\AR\Services\CustomerCreditNoteService;
use App\Domains\AR\Services\CustomerInvoiceService;
use App\Domains\CRM\Models\ClientOrderActivity;
use App\Domains\CRM\Models\ClientOrderDeliverySchedule;
use App\Domains\CRM\Models\ClientOrder;
use App\Domains\CRM\Services\TicketService;
use App\Domains\Delivery\Models\DeliveryDispute;
use App\Domains\Delivery\Models\DeliveryDisputeItem;
use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Production\Models\DeliverySchedule;
use App\Domains\Production\Models\DeliveryScheduleItem;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DeliveryDisputeService -- manages the delivery dispute lifecycle.
 *
 * Disputes are auto-created when a client reports damaged/missing items
 * during delivery acknowledgment. Staff resolves disputes with concrete
 * actions: replace items, issue credit, or accept partial delivery.
 */
final class DeliveryDisputeService implements ServiceContract
{
    public function __construct(
        private readonly CustomerInvoiceService $customerInvoiceService,
    ) {}

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
                    'photo_urls' => $item['photo_urls'] ?? [],
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

        $dispute->loadMissing('items');

        $itemMap = $dispute->items->keyBy('id');
        if (count($resolutions) !== $itemMap->count()) {
            throw new DomainException(
                'Resolution must include all disputed items exactly once.',
                'INCOMPLETE_DISPUTE_RESOLUTION',
                422,
            );
        }

        $normalizedResolutions = [];
        $seenItemIds = [];

        foreach ($resolutions as $resolution) {
            $itemId = (int) ($resolution['item_id'] ?? 0);
            $action = (string) ($resolution['action'] ?? '');
            $qty = (float) ($resolution['qty'] ?? 0);

            if (in_array($itemId, $seenItemIds, true)) {
                throw new DomainException(
                    'Duplicate disputed item in resolution payload.',
                    'DUPLICATE_RESOLUTION_ITEM',
                    422,
                );
            }
            $seenItemIds[] = $itemId;

            /** @var DeliveryDisputeItem|null $disputeItem */
            $disputeItem = $itemMap->get($itemId);
            if (! $disputeItem) {
                throw new DomainException(
                    'Resolution item does not belong to this dispute.',
                    'INVALID_RESOLUTION_ITEM',
                    422,
                );
            }

            if (! in_array($action, ['replace', 'credit', 'accept'], true)) {
                throw new DomainException(
                    'Invalid resolution action.',
                    'INVALID_RESOLUTION_ACTION',
                    422,
                );
            }

            if ($qty < 0) {
                throw new DomainException(
                    'Resolution quantity cannot be negative.',
                    'INVALID_RESOLUTION_QTY_NEGATIVE',
                    422,
                );
            }

            $expectedQty = (float) $disputeItem->expected_qty;
            $receivedQty = (float) $disputeItem->received_qty;
            $disputedQty = max(0.0, $expectedQty - $receivedQty);

            if (in_array($disputeItem->condition, ['missing', 'damaged'], true) && abs($qty - $disputedQty) > 0.0001) {
                throw new DomainException(
                    'Resolution quantity for missing/damaged items must match disputed quantity.',
                    'INVALID_RESOLUTION_QTY_FOR_CONDITION',
                    422,
                );
            }

            if (! in_array($disputeItem->condition, ['missing', 'damaged'], true) && $qty > $expectedQty) {
                throw new DomainException(
                    'Resolution quantity cannot exceed expected quantity.',
                    'INVALID_RESOLUTION_QTY_EXCEEDS_EXPECTED',
                    422,
                );
            }

            $normalizedResolutions[] = [
                'item_id' => $itemId,
                'action' => $action,
                'qty' => $qty,
            ];
        }

        return DB::transaction(function () use ($dispute, $resolutionType, $normalizedResolutions, $resolver, $notes): DeliveryDispute {
            $hasReplace = collect($normalizedResolutions)->contains(fn ($resolution): bool => $resolution['action'] === 'replace');
            $hasCredit = collect($normalizedResolutions)->contains(fn ($resolution): bool => $resolution['action'] === 'credit');
            $allReplace = collect($normalizedResolutions)->every(fn ($resolution): bool => $resolution['action'] === 'replace');
            $allAccept = collect($normalizedResolutions)->every(fn ($resolution): bool => $resolution['action'] === 'accept');

            if ($resolutionType === 'credit_note' && (! $hasCredit || $hasReplace)) {
                throw new DomainException(
                    'Resolution type credit_note requires credit actions only.',
                    'RESOLUTION_TYPE_MISMATCH',
                    422,
                );
            }

            if ($resolutionType === 'partial_accept' && ! $allAccept) {
                throw new DomainException(
                    'Resolution type partial_accept requires accept actions only.',
                    'RESOLUTION_TYPE_MISMATCH',
                    422,
                );
            }

            if ($resolutionType === 'replace_items' && ! $hasReplace) {
                throw new DomainException(
                    'Resolution type replace_items requires at least one replace action.',
                    'RESOLUTION_TYPE_MISMATCH',
                    422,
                );
            }

            if ($resolutionType === 'full_replacement' && ! $allReplace) {
                throw new DomainException(
                    'Resolution type full_replacement requires replace action for all disputed items.',
                    'RESOLUTION_TYPE_MISMATCH',
                    422,
                );
            }

            $resolvedType = $resolutionType;
            if ($resolutionType === 'full_replacement' && $allReplace) {
                $resolvedType = 'full_replacement';
            } elseif ($hasReplace) {
                $resolvedType = 'replace_items';
            } elseif ($hasCredit) {
                $resolvedType = 'credit_note';
            } else {
                $resolvedType = 'partial_accept';
            }

            // Update per-item resolutions
            foreach ($normalizedResolutions as $res) {
                DeliveryDisputeItem::where('delivery_dispute_id', $dispute->id)
                    ->where('id', $res['item_id'])
                    ->update([
                        'resolution_action' => $res['action'],
                        'resolution_qty' => $res['qty'],
                    ]);
            }

            $updateData = [
                'status' => 'resolved',
                'resolution_type' => $resolvedType,
                'resolution_notes' => $notes,
                'resolved_by_id' => $resolver->id,
                'resolved_at' => now(),
            ];

            // Execute resolution action
            if ($hasCredit) {
                $creditNote = $this->createCreditNote($dispute, $normalizedResolutions, $resolver);
                if (! $creditNote) {
                    throw new DomainException(
                        'Unable to create credit note for dispute resolution.',
                        'CREDIT_NOTE_CREATION_FAILED',
                        422,
                    );
                }

                $updateData['credit_note_id'] = $creditNote->id;
            }

            if ($hasReplace) {
                $replacementSchedule = $this->createReplacementSchedule($dispute, $normalizedResolutions, $resolver);
                if (! $replacementSchedule) {
                    throw new DomainException(
                        'Unable to create replacement schedule for dispute resolution.',
                        'REPLACEMENT_SCHEDULE_CREATION_FAILED',
                        422,
                    );
                }

                $updateData['replacement_schedule_id'] = $replacementSchedule->id;
                // Stay pending until replacement is delivered
                $updateData['status'] = 'pending_resolution';
                $updateData['resolved_at'] = null;
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
     * Resolve disputes waiting on replacement delivery once the linked
     * replacement schedule is delivered.
     */
    public function finalizePendingReplacementForSchedule(DeliverySchedule $schedule): void
    {
        $pendingDisputes = DeliveryDispute::query()
            ->where('replacement_schedule_id', $schedule->id)
            ->where('status', 'pending_resolution')
            ->get();

        foreach ($pendingDisputes as $dispute) {
            $dispute->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);

            $this->tryFulfillClientOrder($dispute, 'replacement_delivered');
        }
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

        $dispute->loadMissing(['items', 'deliverySchedule']);
        $disputeItemMap = $dispute->items->keyBy('id');

        $replacementLines = $replaceItems
            ->map(function (array $resolution) use ($disputeItemMap): ?array {
                $disputeItem = $disputeItemMap->get((int) $resolution['item_id']);

                if (! $disputeItem) {
                    return null;
                }

                $qty = (float) $resolution['qty'];
                if ($qty <= 0) {
                    return null;
                }

                return [
                    'product_item_id' => (int) $disputeItem->item_master_id,
                    'qty_ordered' => $qty,
                    'notes' => $disputeItem->notes,
                ];
            })
            ->filter()
            ->values();

        if ($replacementLines->isEmpty()) {
            return null;
        }

        $totalQty = $replacementLines->sum('qty_ordered');
        $sourceSchedule = $dispute->deliverySchedule;

        try {
            // Create a new delivery schedule linked to the same client order
            $schedule = DeliverySchedule::create([
                'ulid' => (string) \Illuminate\Support\Str::ulid(),
                'customer_id' => $dispute->customer_id,
                'client_order_id' => $dispute->client_order_id,
                'product_item_id' => (int) $replacementLines->first()['product_item_id'],
                'qty_ordered' => $totalQty,
                'type' => $sourceSchedule?->type ?? 'local',
                'status' => 'ready',
                'target_delivery_date' => now()->addDays(3)->toDateString(),
                'delivery_address' => $sourceSchedule?->delivery_address,
                'delivery_instructions' => $sourceSchedule?->delivery_instructions,
                'notes' => "Replacement delivery for dispute {$dispute->dispute_reference}",
                'total_items' => $replacementLines->count(),
                'ready_items' => $replacementLines->count(),
                'missing_items' => 0,
                'created_by_id' => $actor->id,
            ]);

            foreach ($replacementLines as $line) {
                DeliveryScheduleItem::create([
                    'delivery_schedule_id' => $schedule->id,
                    'product_item_id' => $line['product_item_id'],
                    'qty_ordered' => $line['qty_ordered'],
                    'status' => 'ready',
                    'notes' => $line['notes']
                        ? "Replacement for {$dispute->dispute_reference}: {$line['notes']}"
                        : "Replacement for {$dispute->dispute_reference}",
                ]);
            }

            $schedule->updateItemStatusSummary();

            if ($dispute->client_order_id) {
                ClientOrderDeliverySchedule::query()->updateOrCreate(
                    [
                        'client_order_id' => $dispute->client_order_id,
                        'delivery_schedule_id' => $schedule->id,
                    ],
                    [
                        'client_order_item_id' => null,
                        'planned_qty' => (float) $totalQty,
                        'fulfilled_qty' => 0,
                        'status' => 'scheduled',
                        'is_primary' => false,
                    ]
                );
            }

            return $schedule;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[DeliveryDispute] Failed to create replacement schedule', [
                'dispute_id' => $dispute->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function tryFulfillClientOrder(DeliveryDispute $dispute, string $activityEvent = 'resolved'): void
    {
        if (! $dispute->client_order_id) {
            return;
        }

        $this->markScheduleDisputeResolved($dispute);
        $this->recordDisputeResolutionActivity($dispute, $activityEvent);

        // Only fulfill if no other open disputes for this order
        if ($this->hasOpenDisputes($dispute->client_order_id)) {
            return;
        }

        $order = ClientOrder::find($dispute->client_order_id);
        if (! $order) {
            return;
        }

        if ($order->status === 'delivered') {
            $order->update(['status' => 'fulfilled']);
        }

        if ($order->status === 'fulfilled') {
            $this->ensureInvoiceForFulfilledOrder($order, $dispute);
        }
    }

    private function ensureInvoiceForFulfilledOrder(ClientOrder $order, DeliveryDispute $dispute): void
    {
        $existingInvoice = DB::table('customer_invoices as ci')
            ->join('delivery_receipts as dr', 'dr.id', '=', 'ci.delivery_receipt_id')
            ->leftJoin('delivery_schedules as ds_by_id', 'ds_by_id.id', '=', 'dr.delivery_schedule_id')
            ->leftJoin('delivery_schedules as ds_by_dr', 'ds_by_dr.delivery_receipt_id', '=', 'dr.id')
            ->whereNull('ci.deleted_at')
            ->where('ci.status', '!=', 'cancelled')
            ->where(function ($query) use ($order): void {
                $query->where('ds_by_id.client_order_id', $order->id)
                    ->orWhere('ds_by_dr.client_order_id', $order->id);
            })
            ->exists();

        if ($existingInvoice) {
            return;
        }

        $subtotal = round(((int) $order->total_amount_centavos) / 100, 2);
        if ($subtotal <= 0) {
            Log::warning('[DeliveryDispute] Auto-invoice skipped after fulfillment due to non-positive subtotal', [
                'client_order_id' => $order->id,
                'dispute_id' => $dispute->id,
            ]);

            return;
        }

        $customer = Customer::find($order->customer_id);
        if (! $customer) {
            Log::warning('[DeliveryDispute] Auto-invoice skipped after fulfillment due to missing customer', [
                'client_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'dispute_id' => $dispute->id,
            ]);

            return;
        }

        $fiscalPeriod = FiscalPeriod::open()
            ->where('date_from', '<=', now()->toDateString())
            ->where('date_to', '>=', now()->toDateString())
            ->latest('date_from')
            ->first() ?? FiscalPeriod::open()->latest('date_from')->first();

        if (! $fiscalPeriod) {
            Log::warning('[DeliveryDispute] Auto-invoice skipped after fulfillment due to missing open fiscal period', [
                'client_order_id' => $order->id,
                'dispute_id' => $dispute->id,
            ]);

            return;
        }

        $arAccountId = (int) json_decode(
            DB::table('system_settings')->where('key', 'default_ar_account_id')->value('value') ?? 'null'
        );
        $revenueAccountId = (int) json_decode(
            DB::table('system_settings')->where('key', 'default_revenue_account_id')->value('value') ?? 'null'
        );

        if (! $arAccountId) {
            $arAccountId = (int) (DB::table('chart_of_accounts')
                ->where('code', '3001')
                ->where('is_active', true)
                ->value('id') ?? 0);
        }

        if (! $revenueAccountId) {
            $revenueAccountId = (int) (DB::table('chart_of_accounts')
                ->where('code', '4001')
                ->where('is_active', true)
                ->value('id') ?? 0);
        }

        if (! $arAccountId || ! $revenueAccountId) {
            Log::warning('[DeliveryDispute] Auto-invoice skipped after fulfillment due to missing AR/revenue account', [
                'client_order_id' => $order->id,
                'ar_account_id' => $arAccountId,
                'revenue_account_id' => $revenueAccountId,
                'dispute_id' => $dispute->id,
            ]);

            return;
        }

        $deliveryReceiptId = DB::table('delivery_receipts as dr')
            ->leftJoin('delivery_schedules as ds_by_id', 'ds_by_id.id', '=', 'dr.delivery_schedule_id')
            ->leftJoin('delivery_schedules as ds_by_dr', 'ds_by_dr.delivery_receipt_id', '=', 'dr.id')
            ->where('dr.status', 'delivered')
            ->where(function ($query) use ($order): void {
                $query->where('ds_by_id.client_order_id', $order->id)
                    ->orWhere('ds_by_dr.client_order_id', $order->id);
            })
            ->orderByDesc('dr.id')
            ->value('dr.id');

        $invoiceDate = $this->clampDateToFiscalPeriod($fiscalPeriod);
        $dueDate = Carbon::parse($invoiceDate)->addDays(30)->toDateString();

        try {
            $this->customerInvoiceService->create(
                customer: $customer,
                data: [
                    'fiscal_period_id' => (int) $fiscalPeriod->id,
                    'ar_account_id' => $arAccountId,
                    'revenue_account_id' => $revenueAccountId,
                    'invoice_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'subtotal' => $subtotal,
                    'vat_amount' => 0,
                    'description' => "Auto-created after dispute {$dispute->dispute_reference} resolution for {$order->order_reference}",
                    'bypass_credit_check' => true,
                    'delivery_receipt_id' => $deliveryReceiptId ? (int) $deliveryReceiptId : null,
                ],
                userId: $dispute->resolved_by_id ?? 1,
            );
        } catch (\Throwable $e) {
            Log::warning('[DeliveryDispute] Auto-invoice creation failed after dispute fulfillment', [
                'client_order_id' => $order->id,
                'dispute_id' => $dispute->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function clampDateToFiscalPeriod(FiscalPeriod $period): string
    {
        $today = now();
        $periodStart = Carbon::parse((string) $period->date_from);
        $periodEnd = Carbon::parse((string) $period->date_to);

        if ($today->lt($periodStart)) {
            return $periodStart->toDateString();
        }

        if ($today->gt($periodEnd)) {
            return $periodEnd->toDateString();
        }

        return $today->toDateString();
    }

    private function markScheduleDisputeResolved(DeliveryDispute $dispute): void
    {
        if (! $dispute->delivery_schedule_id) {
            return;
        }

        $hasOtherOpenScheduleDisputes = DeliveryDispute::query()
            ->where('delivery_schedule_id', $dispute->delivery_schedule_id)
            ->where('id', '!=', $dispute->id)
            ->whereIn('status', ['open', 'investigating', 'pending_resolution'])
            ->exists();

        if ($hasOtherOpenScheduleDisputes) {
            return;
        }

        DeliverySchedule::query()
            ->where('id', $dispute->delivery_schedule_id)
            ->update([
                'has_dispute' => false,
                'dispute_resolved_at' => now(),
            ]);
    }

    private function recordDisputeResolutionActivity(DeliveryDispute $dispute, string $event): void
    {
        if (! $dispute->client_order_id) {
            return;
        }

        $metadata = [
            'delivery_dispute_id' => $dispute->id,
            'delivery_dispute_reference' => $dispute->dispute_reference,
            'event' => $event,
        ];

        if ($dispute->replacement_schedule_id) {
            $metadata['replacement_schedule_id'] = $dispute->replacement_schedule_id;
        }

        ClientOrderActivity::query()->create([
            'client_order_id' => $dispute->client_order_id,
            'user_id' => $dispute->resolved_by_id,
            'user_type' => 'system',
            'action' => ClientOrderActivity::ACTION_NOTE_ADDED,
            'comment' => "Delivery dispute {$dispute->dispute_reference} {$event}.",
            'metadata' => $metadata,
        ]);
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
