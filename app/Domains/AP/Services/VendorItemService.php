<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorItem;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class VendorItemService implements ServiceContract
{
    // ── List ──────────────────────────────────────────────────────────────────

    /**
     * @return LengthAwarePaginator<VendorItem>
     */
    public function list(Vendor $vendor, bool $activeOnly = false, ?string $search = null): LengthAwarePaginator
    {
        $searchTerm = $search !== null ? trim($search) : '';

        return $vendor->vendorItems()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->when(
                $searchTerm !== '',
                fn ($q) => $q->where('item_name', 'ilike', "%{$searchTerm}%")
            )
            ->orderBy('item_code')
            ->paginate(50);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Vendor $vendor, array $data, User $actor): VendorItem
    {
        $this->assertNoDuplicateCode($vendor, $data['item_code']);

        return DB::transaction(function () use ($vendor, $data, $actor): VendorItem {
            return VendorItem::create([
                'vendor_id' => $vendor->id,
                'item_code' => $data['item_code'],
                'item_name' => $data['item_name'],
                'description' => $data['description'] ?? null,
                'unit_of_measure' => $data['unit_of_measure'] ?? 'pc',
                'unit_price' => (int) $data['unit_price'],
                'is_active' => $data['is_active'] ?? true,
                'created_by_id' => $actor->id,
            ]);
        });
    }

    // ── Update ────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(VendorItem $item, array $data): VendorItem
    {
        if (
            isset($data['item_code'])
            && $data['item_code'] !== $item->item_code
        ) {
            $vendor = $item->vendor;
            $this->assertNoDuplicateCode($vendor, $data['item_code'], $item->id);
        }

        $item->update([
            'item_code' => $data['item_code'] ?? $item->item_code,
            'item_name' => $data['item_name'] ?? $item->item_name,
            'description' => $data['description'] ?? $item->description,
            'unit_of_measure' => $data['unit_of_measure'] ?? $item->unit_of_measure,
            'unit_price' => isset($data['unit_price']) ? (int) $data['unit_price'] : $item->unit_price,
            'is_active' => $data['is_active'] ?? $item->is_active,
        ]);

        return $item->fresh();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function delete(VendorItem $item): void
    {
        DB::transaction(fn () => $item->delete());
    }

    // ── Bulk import (CSV / Excel rows) ────────────────────────────────────────

    /**
     * Upsert items by item_code. Rows that do not exist are created;
     * existing rows (matched by vendor_id + item_code) are updated.
     *
     * Expected row keys: item_code, item_name, description?, unit_of_measure?, unit_price, is_active?
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int}
     */
    public function importRows(Vendor $vendor, array $rows, User $actor): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($vendor, $rows, $actor, &$created, &$updated): void {
            foreach ($rows as $row) {
                $itemCode = trim((string) ($row['item_code'] ?? ''));

                if ($itemCode === '') {
                    continue;
                }

                $existing = VendorItem::withTrashed()
                    ->where('vendor_id', $vendor->id)
                    ->where('item_code', $itemCode)
                    ->first();

                if ($existing !== null) {
                    // Restore soft-deleted items on re-import
                    if ($existing->trashed()) {
                        $existing->restore();
                    }

                    $existing->update([
                        'item_name' => trim((string) ($row['item_name'] ?? $existing->item_name)),
                        'description' => $row['description'] ?? $existing->description,
                        'unit_of_measure' => trim((string) ($row['unit_of_measure'] ?? $existing->unit_of_measure)),
                        'unit_price' => isset($row['unit_price']) ? (int) round((float) $row['unit_price'] * 100) : $existing->unit_price,
                        'is_active' => isset($row['is_active']) ? (bool) $row['is_active'] : $existing->is_active,
                    ]);

                    $updated++;
                } else {
                    VendorItem::create([
                        'vendor_id' => $vendor->id,
                        'item_code' => $itemCode,
                        'item_name' => trim((string) ($row['item_name'] ?? '')),
                        'description' => $row['description'] ?? null,
                        'unit_of_measure' => trim((string) ($row['unit_of_measure'] ?? 'pc')),
                        'unit_price' => isset($row['unit_price']) ? (int) round((float) $row['unit_price'] * 100) : 0,
                        'is_active' => isset($row['is_active']) ? (bool) $row['is_active'] : true,
                        'created_by_id' => $actor->id,
                    ]);

                    $created++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function assertNoDuplicateCode(Vendor $vendor, string $itemCode, ?int $excludeId = null): void
    {
        $exists = VendorItem::where('vendor_id', $vendor->id)
            ->where('item_code', $itemCode)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->withTrashed()
            ->exists();

        if ($exists) {
            throw new DomainException(
                message: "Item code '{$itemCode}' already exists for this vendor.",
                errorCode: 'VENDOR_ITEM_CODE_DUPLICATE',
                httpStatus: 422,
            );
        }
    }
}
