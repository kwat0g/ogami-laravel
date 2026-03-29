<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Database\Eloquent\Collection;

final class ItemMasterService implements ServiceContract
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): ItemMaster
    {
        // Auto-generate item code if not provided: PREFIX-NNNNN
        $itemCode = $data['item_code'] ?? $this->generateItemCode($data['type'] ?? 'raw_material');

        return ItemMaster::create([
            'item_code' => $itemCode,
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'unit_of_measure' => $data['unit_of_measure'],
            'description' => $data['description'] ?? null,
            'standard_price_centavos' => $data['standard_price_centavos'] ?? 0,
            'reorder_point' => $data['reorder_point'] ?? 0,
            'reorder_qty' => $data['reorder_qty'] ?? 0,
            'type' => $data['type'] ?? 'raw_material',
            'requires_iqc' => $data['requires_iqc'] ?? false,
            'is_active' => true,
        ]);
    }

    /**
     * Auto-generate an item code based on item type.
     *
     * Format: PREFIX-NNNNN (e.g., RM-00042, FG-00015, SP-00003)
     */
    private function generateItemCode(string $type): string
    {
        $prefixes = [
            'raw_material' => 'RM',
            'semi_finished' => 'SF',
            'finished_good' => 'FG',
            'consumable' => 'CON',
            'spare_part' => 'SP',
        ];

        $prefix = $prefixes[$type] ?? 'ITM';

        // Find the highest existing number for this prefix
        $latest = ItemMaster::where('item_code', 'like', $prefix . '-%')
            ->orderByRaw("CAST(SUBSTRING(item_code FROM '\\d+$') AS INTEGER) DESC NULLS LAST")
            ->value('item_code');

        if ($latest !== null && preg_match('/(\d+)$/', $latest, $matches)) {
            $nextNum = ((int) $matches[1]) + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . '-' . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ItemMaster $item, array $data): ItemMaster
    {
        $updateData = [
            'category_id' => $data['category_id'] ?? $item->category_id,
            'name' => $data['name'] ?? $item->name,
            'unit_of_measure' => $data['unit_of_measure'] ?? $item->unit_of_measure,
            'description' => $data['description'] ?? $item->description,
            'reorder_point' => $data['reorder_point'] ?? $item->reorder_point,
            'reorder_qty' => $data['reorder_qty'] ?? $item->reorder_qty,
            'type' => $data['type'] ?? $item->type,
            'requires_iqc' => $data['requires_iqc'] ?? $item->requires_iqc,
        ];

        if (array_key_exists('standard_price_centavos', $data)) {
            $updateData['standard_price_centavos'] = $data['standard_price_centavos'];
        }

        $item->update($updateData);

        return $item->refresh();
    }

    public function toggleActive(ItemMaster $item): ItemMaster
    {
        $item->update(['is_active' => ! $item->is_active]);

        return $item->refresh();
    }

    /** @return Collection<int, ItemMaster> */
    public function lowStockItems(): Collection
    {
        return ItemMaster::query()
            ->where('is_active', true)
            ->whereRaw('CAST(reorder_point AS NUMERIC) > (SELECT COALESCE(SUM(CAST(sb.quantity_on_hand AS NUMERIC)), 0) FROM stock_balances as sb WHERE sb.item_id = item_masters.id)')
            ->with('category')
            ->get();
    }

    /** @return Collection<int, ItemCategory> */
    public function allCategories(): Collection
    {
        return ItemCategory::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeCategory(array $data): ItemCategory
    {
        return ItemCategory::create([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
    }
}
