<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounting;

use App\Domains\Accounting\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChartOfAccount */
final class ChartOfAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'account_type' => $this->account_type,
            'parent_id' => $this->parent_id,
            'normal_balance' => $this->normal_balance,
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
            'description' => $this->description,
            'is_leaf' => $this->whenLoaded('children', fn () => $this->children->isEmpty(), null),
            'children' => ChartOfAccountResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
