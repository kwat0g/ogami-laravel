<?php

declare(strict_types=1);

namespace App\Http\Resources\FixedAssets;

use App\Domains\FixedAssets\Models\FixedAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MED-001: Standardized resource for FixedAsset.
 *
 * @mixin FixedAsset
 */
final class FixedAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'asset_code' => $this->asset_code,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => new FixedAssetCategoryResource($this->category)),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'code' => $this->department->code,
            ]),
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'acquisition_cost_centavos' => $this->acquisition_cost_centavos,
            'acquisition_cost' => $this->acquisition_cost_centavos / 100,
            'status' => $this->status,
            'useful_life_years' => $this->useful_life_years,
            'depreciation_method' => $this->depreciation_method,
            'residual_value_centavos' => $this->residual_value_centavos,
            'accumulated_depreciation_centavos' => $this->accumulated_depreciation_centavos,
            'residual_value' => $this->residual_value_centavos / 100,

            'depreciation_start_date' => $this->depreciation_start_date?->toDateString(),
            'location' => $this->location,
            'serial_number' => $this->serial_number,
            'created_by_id' => $this->created_by_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'depreciation_entries' => $this->whenLoaded('depreciationEntries', fn () => $this->depreciationEntries->map(fn ($entry) => [
                'id' => $entry->id,
                'fiscal_period_id' => $entry->fiscal_period_id,
                'fiscal_period' => $entry->fiscalPeriod?->name,
                'depreciation_amount_centavos' => $entry->depreciation_amount_centavos,
                'depreciation_amount' => $entry->depreciation_amount_centavos / 100,
                'book_value_after_centavos' => $entry->book_value_after_centavos,
                'book_value_after' => $entry->book_value_after_centavos / 100,
                'posted_at' => $entry->posted_at?->toIso8601String(),
            ])
            ),
            'disposal' => $this->whenLoaded('disposal', fn () => [
                'id' => $this->disposal->id,
                'disposal_date' => $this->disposal->disposal_date?->toDateString(),
                'proceeds_centavos' => $this->disposal->proceeds_centavos,
                'proceeds' => $this->disposal->proceeds_centavos / 100,
                'gain_loss_centavos' => $this->disposal->gain_loss_centavos,
                'gain_loss' => $this->disposal->gain_loss_centavos / 100,
            ]),
        ];
    }
}
