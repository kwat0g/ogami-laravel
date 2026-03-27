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

    public function accept(Quotation $quotation): Quotation
    {
        if ($quotation->status !== 'sent') {
            throw new DomainException('Quotation must be sent to accept.', 'SALES_INVALID_QUOTATION_STATUS', 422);
        }

        $quotation->update(['status' => 'accepted']);

        return $quotation->fresh() ?? $quotation;
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
