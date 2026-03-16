<?php

declare(strict_types=1);

namespace App\Http\Resources\FixedAssets;

use App\Domains\FixedAssets\Models\FixedAssetCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MED-001: Standardized resource for FixedAssetCategory.
 *
 * @mixin FixedAssetCategory
 */
final class FixedAssetCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'code_prefix' => $this->code_prefix,
            'default_depreciation_method' => $this->default_depreciation_method,
            'default_useful_life_years' => $this->default_useful_life_years,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
