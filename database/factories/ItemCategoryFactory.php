<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Inventory\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemCategory>
 */
final class ItemCategoryFactory extends Factory
{
    protected $model = ItemCategory::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'code' => 'CAT-'.$seq,
            'name' => 'Item Category '.$seq,
            'description' => 'Test category description',
            'is_active' => true,
        ];
    }
}
