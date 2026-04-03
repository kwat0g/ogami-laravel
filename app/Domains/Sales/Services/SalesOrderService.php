<?php

declare(strict_types=1);

namespace App\Domains\Sales\Services;

use App\Domains\AR\Models\Customer;
use App\Domains\Sales\Models\Quotation;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderItem;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\CreditLimitExceededException;
use App\Events\Sales\SalesOrderConfirmed;
use App\Shared\Exceptions\DomainException;
use App\Shared\Traits\HasArchiveOperations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class SalesOrderService implements ServiceContract
{
    use HasArchiveOperations;
    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = SalesOrder::with(['customer', 'contact', 'createdBy'])
            ->orderByDesc('id');

        if ($filters['search'] ?? null) {
            $v = $filters['search'];
            $query->where(fn ($q) => $q->where('order_number', 'ilike', "%{$v}%")->orWhereHas('customer', fn ($q2) => $q2->where('name', 'ilike', "%{$v}%")));
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    // ── Archive / Restore / Force Delete ────────────────────────────────────

    public function archive(SalesOrder $order, User $user): void
    {
        $this->archiveRecord($order, $user);
    }

    public function restore(int $id, User $user): SalesOrder
    {
        /** @var SalesOrder */
        return $this->restoreRecord(SalesOrder::class, $id, $user);
    }

    public function forceDelete(int $id, User $user): void
    {
        $this->forceDeleteRecord(SalesOrder::class, $id, $user);
    }

    public function listArchived(int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return $this->listArchivedRecords(SalesOrder::class, $perPage, $search, ['order_number']);
    }

    protected function dependentRelationships(Model $model): array
    {
        return ['items' => 'Order Items'];
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, User $actor): SalesOrder
    {
        // SAL-S12: Credit limit soft-block — warn if customer exceeds credit limit
        $this->checkCreditLimit($data);

        return DB::transaction(function () use ($data, $actor): SalesOrder {
            $order = SalesOrder::create([
                'order_number' => $data['order_number'] ?? 'SO-' . now()->format('Ymd-His'),
                'customer_id' => $data['customer_id'],
                'contact_id' => $data['contact_id'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'status' => 'draft',
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'promised_delivery_date' => $data['promised_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_id' => $actor->id,
            ]);

            $total = 0;
            foreach ($data['items'] ?? [] as $item) {
                $lineTotal = (int) round((float) $item['quantity'] * $item['unit_price_centavos']);
                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit_price_centavos' => $item['unit_price_centavos'],
                    'line_total_centavos' => $lineTotal,
                    'remarks' => $item['remarks'] ?? null,
                ]);
                $total += $lineTotal;
            }

            $order->update(['total_centavos' => $total]);

            return $order->load('items.item', 'customer', 'contact');
        });
    }

    /**
     * Create a Sales Order from an accepted Quotation.
     */
    public function createFromQuotation(Quotation $quotation, User $actor): SalesOrder
    {
        if ($quotation->status !== 'accepted') {
            throw new DomainException(
                'Quotation must be accepted before converting to sales order.',
                'SALES_QUOTATION_NOT_ACCEPTED',
                422
            );
        }

        return DB::transaction(function () use ($quotation, $actor): SalesOrder {
            $quotation->loadMissing('items');

            $items = $quotation->items->map(fn ($qi) => [
                'item_id' => $qi->item_id,
                'quantity' => $qi->quantity,
                'unit_price_centavos' => $qi->unit_price_centavos,
                'remarks' => $qi->remarks,
            ])->toArray();

            $order = $this->store([
                'customer_id' => $quotation->customer_id,
                'contact_id' => $quotation->contact_id,
                'quotation_id' => $quotation->id,
                'opportunity_id' => $quotation->opportunity_id,
                'notes' => "Created from quotation #{$quotation->quotation_number}",
                'items' => $items,
            ], $actor);

            $quotation->update(['status' => 'converted_to_order']);

            return $order;
        });
    }

    /**
     * Confirm a Sales Order and trigger downstream fulfillment.
     *
     * Flexibility — multiple fulfillment paths:
     *   - Make-to-stock: if all items have sufficient stock, reserve and create DR
     *   - Make-to-order: if stock insufficient, auto-create Production Orders
     *   - Partial: some lines from stock, others to production
     *
     * Credit check enforced via Customer.enforceCredit() when credit_limit > 0.
     * Controlled by system_setting 'automation.so_confirmed.auto_create_production'.
     */
    public function confirm(SalesOrder $order, User $approver): SalesOrder
    {
        if ($order->status !== 'draft') {
            throw new DomainException('Sales order must be in draft to confirm.', 'SALES_INVALID_ORDER_STATUS', 422);
        }

        // SoD: creator cannot confirm their own sales order
        if ($approver->id === $order->created_by_id) {
            throw new DomainException(
                'You cannot confirm a sales order you created (Separation of Duties).',
                'SALES_SOD_SELF_CONFIRM',
                403,
            );
        }

        $order->loadMissing(['items.item', 'customer']);

        // ── Credit limit enforcement ──────────────────────────────────────
        $customer = $order->customer;
        if ($customer !== null) {
            try {
                $totalAmount = ((float) $order->total_centavos) / 100;
                $customer->assertCreditAvailable($totalAmount);
            } catch (\Throwable $e) {
                // Check if soft limit mode — warn but allow
                $softLimit = (bool) (DB::table('system_settings')
                    ->where('key', 'credit.soft_limit_warn_only')
                    ->value('value') ?? false);

                if (! $softLimit) {
                    throw $e; // Hard block
                }
                // Soft limit: log warning but proceed
                \Illuminate\Support\Facades\Log::warning('[Sales] Credit limit exceeded (soft mode)', [
                    'sales_order_id' => $order->id,
                    'customer_id' => $customer->id,
                ]);
            }
        }

        $confirmed = DB::transaction(function () use ($order, $approver): SalesOrder {
            $order->update([
                'status' => 'confirmed',
                'approved_by_id' => $approver->id,
                'approved_at' => now(),
            ]);

            // ── Auto-fulfillment chain ───────────────────────────────────────
            $autoCreate = (bool) (DB::table('system_settings')
                ->where('key', 'automation.so_confirmed.auto_create_production')
                ->value('value') ?? true);

            if ($autoCreate) {
                $this->triggerFulfillment($order);
            }

            return $order->fresh(['items.item', 'customer']) ?? $order;
        });

        // CHAIN-SO-DR-001: Fire event after commit so queued listener can auto-create DR
        event(new SalesOrderConfirmed($confirmed));

        return $confirmed;
    }

    /**
     * Trigger fulfillment for confirmed SO items.
     *
     * For each line item:
     *   1. Check available stock (from StockBalance)
     *   2. If sufficient: reserve stock, mark line as ready for delivery
     *   3. If insufficient: create a Production Order for the deficit
     *      (requires active BOM for the item)
     *
     * FS-030 FIX: Collects warnings on the order model attribute so the API
     * response can surface non-fatal failures to the user.
     */
    private function triggerFulfillment(SalesOrder $order): void
    {
        $warnings = [];
        $order->loadMissing('items.item');
        $hasProduction = false;

        foreach ($order->items as $soItem) {
            $item = $soItem->item;
            if ($item === null) {
                continue;
            }

            $qtyNeeded = (float) $soItem->quantity;
            $availableStock = (float) \App\Domains\Inventory\Models\StockBalance::query()
                ->where('item_id', $item->id)
                ->sum('quantity_on_hand');

            if ($availableStock >= $qtyNeeded) {
                // Make-to-stock: reserve stock
                try {
                    $reservationService = app(\App\Domains\Inventory\Services\StockReservationService::class);
                    $reservationService->createReservation(
                        itemId: $item->id,
                        quantity: $qtyNeeded,
                        reservationType: 'sales_order',
                        referenceId: $order->id,
                        referenceType: 'sales_orders',
                        notes: "Reserved for SO {$order->order_number}",
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[Sales] Stock reservation failed', [
                        'sales_order_id' => $order->id,
                        'item_id' => $item->id,
                        'error' => $e->getMessage(),
                    ]);
                    $warnings[] = "Stock reservation failed for {$item->name}: {$e->getMessage()}";
                }
            } else {
                // Make-to-order: create production order for deficit
                $deficit = $qtyNeeded - max(0, $availableStock);

                // Reserve whatever stock is available
                if ($availableStock > 0) {
                    try {
                        $reservationService = app(\App\Domains\Inventory\Services\StockReservationService::class);
                        $reservationService->createReservation(
                            itemId: $item->id,
                            quantity: $availableStock,
                            reservationType: 'sales_order',
                            referenceId: $order->id,
                            referenceType: 'sales_orders',
                            notes: "Partial reservation for SO {$order->order_number} ({$availableStock} of {$qtyNeeded})",
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('[Sales] Partial reservation failed', [
                            'sales_order_id' => $order->id,
                            'item_id' => $item->id,
                            'error' => $e->getMessage(),
                        ]);
                        $warnings[] = "Partial stock reservation failed for {$item->name}: {$e->getMessage()}";
                    }
                }

                // Find active BOM for the item
                $bom = \App\Domains\Production\Models\BillOfMaterials::where('product_item_id', $item->id)
                    ->where('is_active', true)
                    ->first();

                if ($bom !== null) {
                    try {
                        $poService = app(\App\Domains\Production\Services\ProductionOrderService::class);
                        $actor = \App\Models\User::find($order->approved_by_id ?? $order->created_by_id);
                        if ($actor !== null) {
                            $poService->store([
                                'product_item_id' => $item->id,
                                'bom_id' => $bom->id,
                                'qty_required' => $deficit,
                                'target_start_date' => now()->toDateString(),
                                'target_end_date' => $order->promised_delivery_date ?? now()->addDays(14)->toDateString(),
                                'sales_order_id' => $order->id,
                                'source_type' => 'sales_order',
                                'source_id' => $order->id,
                                'notes' => "Auto-created from Sales Order {$order->order_number} (deficit: {$deficit} units)",
                            ], $actor);
                        }
                        $hasProduction = true;
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('[Sales] Auto production order creation failed', [
                            'sales_order_id' => $order->id,
                            'item_id' => $item->id,
                            'error' => $e->getMessage(),
                        ]);
                        $warnings[] = "Auto production order creation failed for {$item->name}: {$e->getMessage()}. Create manually.";
                    }
                } else {
                    \Illuminate\Support\Facades\Log::warning('[Sales] No active BOM for item, cannot auto-create production order', [
                        'sales_order_id' => $order->id,
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                    ]);
                    $warnings[] = "No active BOM found for {$item->name} — cannot auto-create production order. Set up a BOM or create the production order manually.";
                }
            }
        }

        // Update SO status based on fulfillment path
        if ($hasProduction) {
            $order->update(['status' => 'in_production']);
        }

        // FS-030 FIX: Attach warnings to order so API response surfaces them
        if (! empty($warnings)) {
            $order->setAttribute('_confirm_warnings', $warnings);
        }
    }

    /**
     * FS-013 FIX: Mark a confirmed/in_production SO as partially delivered.
     */
    public function markPartiallyDelivered(SalesOrder $order): SalesOrder
    {
        if (! in_array($order->status, ['confirmed', 'in_production'], true)) {
            throw new DomainException(
                "Cannot mark as partially delivered from status '{$order->status}'.",
                'SALES_INVALID_ORDER_STATUS',
                422,
            );
        }

        $order->update(['status' => 'partially_delivered']);

        return $order->fresh() ?? $order;
    }

    /**
     * FS-013 FIX: Mark a SO as fully delivered.
     */
    public function markDelivered(SalesOrder $order): SalesOrder
    {
        if (! in_array($order->status, ['confirmed', 'in_production', 'partially_delivered'], true)) {
            throw new DomainException(
                "Cannot mark as delivered from status '{$order->status}'.",
                'SALES_INVALID_ORDER_STATUS',
                422,
            );
        }

        $order->update(['status' => 'delivered']);

        return $order->fresh() ?? $order;
    }

    /**
     * FS-013 FIX: Mark a delivered SO as invoiced (after AR invoice creation).
     */
    public function markInvoiced(SalesOrder $order): SalesOrder
    {
        if ($order->status !== 'delivered') {
            throw new DomainException(
                "Cannot mark as invoiced from status '{$order->status}'. Order must be delivered first.",
                'SALES_INVALID_ORDER_STATUS',
                422,
            );
        }

        $order->update(['status' => 'invoiced']);

        return $order->fresh() ?? $order;
    }

    public function cancel(SalesOrder $order): SalesOrder
    {
        if (in_array($order->status, ['delivered', 'invoiced', 'cancelled'], true)) {
            throw new DomainException('Cannot cancel order in current status.', 'SALES_INVALID_ORDER_STATUS', 422);
        }

        $order->update(['status' => 'cancelled']);

        return $order->fresh() ?? $order;
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    /**
     * SAL-S12: Credit limit soft-block with approval override.
     *
     * Checks if the customer's outstanding AR balance + this order total
     * would exceed their credit limit. If so, throws CreditLimitExceededException
     * which the controller can catch and return as a warning requiring override.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws CreditLimitExceededException
     */
    private function checkCreditLimit(array $data): void
    {
        $customerId = $data['customer_id'] ?? null;
        if ($customerId === null) {
            return;
        }

        // Allow explicit override (e.g., manager approved despite credit limit)
        if (($data['override_credit_limit'] ?? false) === true) {
            return;
        }

        $customer = Customer::find($customerId);
        if ($customer === null || (float) $customer->credit_limit <= 0) {
            return; // No credit limit configured — no check needed
        }

        $creditLimitCentavos = (int) round((float) $customer->credit_limit * 100);

        // Calculate outstanding AR balance (unpaid invoices)
        $outstandingCentavos = (int) DB::table('customer_invoices')
            ->where('customer_id', $customerId)
            ->whereIn('status', ['approved', 'sent', 'overdue'])
            ->selectRaw('COALESCE(SUM(CAST(total_amount * 100 AS BIGINT)), 0) as total')
            ->value('total');

        // Calculate this order's estimated total
        $orderTotalCentavos = 0;
        foreach ($data['items'] ?? [] as $item) {
            $orderTotalCentavos += (int) round((float) ($item['quantity'] ?? 0) * ($item['unit_price_centavos'] ?? 0));
        }

        $projectedTotal = $outstandingCentavos + $orderTotalCentavos;

        if ($projectedTotal > $creditLimitCentavos) {
            throw new CreditLimitExceededException(
                customerName: $customer->name,
                currentOutstanding: $outstandingCentavos / 100,
                creditLimit: (float) $customer->credit_limit,
                invoiceAmount: $orderTotalCentavos / 100,
            );
        }
    }
}
