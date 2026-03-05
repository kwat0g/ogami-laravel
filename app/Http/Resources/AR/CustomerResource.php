<?php

declare(strict_types=1);

namespace App\Http\Resources\AR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domains\AR\Models\Customer
 */
final class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \App\Domains\AR\Models\Customer $c */
        $c = $this->resource;

        return [
            'id' => $c->id,
            'name' => $c->name,
            'tin' => $c->tin,
            'email' => $c->email,
            'phone' => $c->phone,
            'contact_person' => $c->contact_person,
            'address' => $c->address,
            'billing_address' => $c->billing_address,
            // AR-001: credit exposure info for the frontend credit meter
            'credit_limit' => (float) $c->credit_limit,
            'current_outstanding' => $c->current_outstanding,   // AR-004 computed
            'available_credit' => $c->available_credit,
            'is_active' => $c->is_active,
            'ar_account_id' => $c->ar_account_id,
            'notes' => $c->notes,
            'created_at' => $c->created_at->toIso8601String(),
            'updated_at' => $c->updated_at->toIso8601String(),
        ];
    }
}
