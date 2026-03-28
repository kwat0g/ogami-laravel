<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemMaster>
 */
final class ItemMasterFactory extends Factory
{
    protected $model = ItemMaster::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'item_code' => 'ITEM-'.$seq,
            'category_id' => ItemCategory::factory(),
            'name' => 'Item '.$this->faker->word().' '.$seq,
            'unit_of_measure' => 'pcs',
            'description' => 'Test item description',
            'standard_price_centavos' => $this->faker->numberBetween(100, 50000),
            'reorder_point' => 10,
            'reorder_qty' => 50,
            'type' => 'raw_material',
            'requires_iqc' => false,
            'is_active' => true,
        ];
    }
}
