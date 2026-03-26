<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\FixedAssets\Models\FixedAsset;
use App\Domains\FixedAssets\Models\FixedAssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedAsset>
 */
final class FixedAssetFactory extends Factory
{
    protected $model = FixedAsset::class;

    public function definition(): array
    {
        return [
            'category_id' => FixedAssetCategory::factory(),
            'department_id' => null,
            'name' => 'Asset '.$this->faker->word(),
            'description' => $this->faker->sentence(),
            'serial_number' => $this->faker->uuid(),
            'location' => $this->faker->word(),
            'acquisition_date' => now()->subYear(),
            'acquisition_cost_centavos' => 10000000, // ₱100,000
            'residual_value_centavos' => 1000000, // ₱10,000
            'useful_life_years' => 5,
            'depreciation_method' => 'straight_line',
            'accumulated_depreciation_centavos' => 0,
            'status' => 'active',
            'purchase_invoice_ref' => null,
            'purchased_from' => null,
            'disposal_date' => null,
        ];
    }
}
