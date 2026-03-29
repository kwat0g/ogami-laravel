<?php

declare(strict_types=1);

namespace App\Http\Resources\Attendance;

use App\Domains\Attendance\Models\WorkLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkLocation
 */
final class WorkLocationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var WorkLocation $wl */
        $wl = $this->resource;

        return [
            'id' => $wl->id,
            'ulid' => $wl->ulid,
            'name' => $wl->name,
            'code' => $wl->code,
            'address' => $wl->address,
            'city' => $wl->city,
            'latitude' => (float) $wl->latitude,
            'longitude' => (float) $wl->longitude,
            'radius_meters' => $wl->radius_meters,
            'allowed_variance_meters' => $wl->allowed_variance_meters,
            'is_remote_allowed' => $wl->is_remote_allowed,
            'is_active' => $wl->is_active,
            'created_at' => $wl->created_at?->toIso8601String(),
            'updated_at' => $wl->updated_at?->toIso8601String(),
        ];
    }
}
