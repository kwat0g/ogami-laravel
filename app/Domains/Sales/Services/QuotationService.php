<?php

declare(strict_types=1);

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\Quotation;
use App\Domains\Sales\Models\QuotationItem;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class QuotationService implements ServiceContract
{
    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Quotation::with(['customer', 'contact', 'createdBy'])
            ->orderByDesc('id');

        if ($filters['search'] ?? null) {
            $v = $filters['search'];
            $query->where(fn ($q) => $q->where('quotation_number', 'ilike', "%{$v}%")->orWhereHas('customer', fn ($q2) => $q2->where('name', 'ilike', "%{$v}%")));
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, User $actor): Quotation
    {
        return DB::transaction(function () use ($data, $actor): Quotation {
            $quotation = Quotation::create([
                'quotation_number' => $data['quotation_number'] ?? 'QT-' . now()->format('Ymd-His'),
                'customer_id' => $data['customer_id'],
                'contact_id' => $data['contact_id'] ?? null,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'validity_date' => $data['validity_date'],
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
                'created_by_id' => $actor->id,
            ]);

            $total = 0;
            foreach ($data['items'] ?? [] as $item) {
                $lineTotal = (int) round((float) $item['quantity'] * $item['unit_price_centavos']);
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit_price_centavos' => $item['unit_price_centavos'],
                    'line_total_centavos' => $lineTotal,
                    'remarks' => $item['remarks'] ?? null,
                ]);
                $total += $lineTotal;
            }

            $quotation->update(['total_centavos' => $total]);

            return $quotation->load('items.item', 'customer', 'contact');
        });
    }

    public function send(Quotation $quotation): Quotation
    {
        if ($quotation->status !== 'draft') {
            throw new DomainException('Quotation must be in draft to send.', 'SALES_INVALID_QUOTATION_STATUS', 422);
        }

        $quotation->update(['status' => 'sent']);

        return $quotation->fresh() ?? $quotation;
    }

    /**
     * Accept a quotation — auto-creates Sales Order if enabled (A2 automation).
     *
     * Controlled by system_setting 'automation.quotation_accepted.auto_create_so'.
     * When enabled, accepting a quotation immediately creates a confirmed SO
     * which triggers the downstream fulfillment chain (stock reservation or production).
     */
    public function accept(Quotation $quotation): Quotation
    {
        if ($quotation->status !== 'sent') {
            throw new DomainException('Quotation must be sent to accept.', 'SALES_INVALID_QUOTATION_STATUS', 422);
        }

        return DB::transaction(function () use ($quotation): Quotation {
            $quotation->update(['status' => 'accepted']);

            // A2: Auto-create Sales Order from accepted quotation
            $autoCreate = (bool) (DB::table('system_settings')
                ->where('key', 'automation.quotation_accepted.auto_create_so')
                ->value('value') ?? true);

            if ($autoCreate) {
                try {
                    $actor = $quotation->createdBy ?? \App\Models\User::find($quotation->created_by_id);
                    if ($actor) {
                        $soService = app(SalesOrderService::class);
                        $soService->createFromQuotation($quotation, $actor);

                        \Illuminate\Support\Facades\Log::info('[Sales] Auto-created SO from accepted quotation', [
                            'quotation_id' => $quotation->id,
                            'quotation_number' => $quotation->quotation_number,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Don't fail quotation acceptance if SO creation fails
                    \Illuminate\Support\Facades\Log::warning('[Sales] Auto SO creation from quotation failed', [
                        'quotation_id' => $quotation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $quotation->fresh() ?? $quotation;
        });
    }

    public function reject(Quotation $quotation): Quotation
    {
        if (! in_array($quotation->status, ['draft', 'sent'], true)) {
            throw new DomainException('Quotation cannot be rejected in current status.', 'SALES_INVALID_QUOTATION_STATUS', 422);
        }

        $quotation->update(['status' => 'rejected']);

        return $quotation->fresh() ?? $quotation;
    }
}
