<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\FixedAssets\Models\FixedAssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedAssetCategory>
 */
final class FixedAssetCategoryFactory extends Factory
{
    protected $model = FixedAssetCategory::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'name' => 'Category '.$this->faker->word().' '.$seq,
            'code_prefix' => 'CAT'.$seq,
            'default_useful_life_years' => 5,
            'default_depreciation_method' => 'straight_line',
        ];
    }
}
