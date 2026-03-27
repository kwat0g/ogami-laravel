<?php

declare(strict_types=1);

namespace App\Http\Resources\Budget;

use App\Domains\Budget\Models\CostCenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CostCenterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CostCenter $cc */
        $cc = $this->resource;

        return [
            'id' => $cc->id,
            'ulid' => $cc->ulid,
            'name' => $cc->name,
            'code' => $cc->code,
            'description' => $cc->description,
            'department_id' => $cc->department_id,
            'department' => $this->whenLoaded('department', fn () => $cc->department ? [
                'id' => $cc->department->id,
                'name' => $cc->department->name,
            ] : null),
            'parent_id' => $cc->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => $cc->parent ? [
                'id' => $cc->parent->id,
                'name' => $cc->parent->name,
                'code' => $cc->parent->code,
            ] : null),
            'is_active' => $cc->is_active,
            'created_by_id' => $cc->created_by_id,
            'created_at' => $cc->created_at,
            'updated_at' => $cc->updated_at,
        ];
    }
}
