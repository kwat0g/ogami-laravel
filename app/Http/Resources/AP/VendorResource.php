<?php

declare(strict_types=1);

namespace App\Http\Resources\AP;

use App\Domains\AP\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vendor
 */
final class VendorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Vendor $v */
        $v = $this->resource;
        $portalUser = $v->relationLoaded('portalUser') ? $v->portalUser : null;
        $canManageUsers = $request->user()?->hasPermissionTo('system.manage_users') ?? false;

        return [
            'id' => $v->id,
            'name' => $v->name,
            'tin' => $v->tin,
            'atc_code' => $v->atc_code,
            'ewt_rate_id' => $v->ewt_rate_id,
            'ewt_rate' => $this->whenLoaded('ewtRate', fn () => [
                'id' => $v->ewtRate->id,
                'atc_code' => $v->ewtRate->atc_code,
                'description' => $v->ewtRate->description,
                'rate' => (float) $v->ewtRate->rate,
            ]),
            'is_ewt_subject' => $v->is_ewt_subject,
            'is_active' => $v->is_active,
            'address' => $v->address,
            'contact_person' => $v->contact_person,
            'email' => $v->email,
            'phone' => $v->phone,
            'notes' => $v->notes,
            'accreditation_status' => $v->accreditation_status ?? 'pending',
            'accreditation_notes' => $v->accreditation_notes,
            'bank_name' => $v->bank_name,
            'bank_account_no' => $v->bank_account_no,
            'bank_account_name' => $v->bank_account_name,
            'payment_terms' => $v->payment_terms,
            'portal_account_exists' => $portalUser !== null,
            'portal_account_email' => $canManageUsers ? $portalUser?->email : null,
            'created_at' => $v->created_at->toIso8601String(),
            'updated_at' => $v->updated_at->toIso8601String(),
        ];
    }
}
