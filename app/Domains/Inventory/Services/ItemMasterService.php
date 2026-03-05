<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Database\Eloquent\Collection;

final class ItemMasterService implements ServiceContract
{
    /**
     * @param array<string, mixed> $data
     */
    public function store(array $data): ItemMaster
    {
        return ItemMaster::create([
            'category_id'      => $data['category_id'],
            'name'             => $data['name'],
            'unit_of_measure'  => $data['unit_of_measure'],
            'description'      => $data['description'] ?? null,
            'reorder_point'    => $data['reorder_point'] ?? 0,
            'reorder_qty'      => $data['reorder_qty'] ?? 0,
            'type'             => $data['type'] ?? 'raw_material',
            'requires_iqc'     => $data['requires_iqc'] ?? false,
            'is_active'        => true,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(ItemMaster $item, array $data): ItemMaster
    {
        $item->update([
            'category_id'     => $data['category_id'] ?? $item->category_id,
            'name'            => $data['name'] ?? $item->name,
            'unit_of_measure' => $data['unit_of_measure'] ?? $item->unit_of_measure,
            'description'     => $data['description'] ?? $item->description,
            'reorder_point'   => $data['reorder_point'] ?? $item->reorder_point,
            'reorder_qty'     => $data['reorder_qty'] ?? $item->reorder_qty,
            'type'            => $data['type'] ?? $item->type,
            'requires_iqc'    => $data['requires_iqc'] ?? $item->requires_iqc,
        ]);

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
            ->whereColumn('reorder_point', '>', function ($query): void {
                $query->selectRaw('COALESCE(SUM(sb.quantity_on_hand), 0)')
                    ->from('stock_balances as sb')
                    ->whereColumn('sb.item_id', 'item_masters.id');
            })
            ->with('category')
            ->get();
    }

    /** @return Collection<int, ItemCategory> */
    public function allCategories(): Collection
    {
        return ItemCategory::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function storeCategory(array $data): ItemCategory
    {
        return ItemCategory::create([
            'code'        => strtoupper($data['code']),
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
    }
}
