<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Domains\Sales\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;

final class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Quotation::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'contact_id' => ['sometimes', 'integer', 'exists:crm_contacts,id'],
            'opportunity_id' => ['sometimes', 'integer', 'exists:crm_opportunities,id'],
            'validity_date' => ['required', 'date', 'after:today'],
            'notes' => ['sometimes', 'string'],
            'terms_and_conditions' => ['sometimes', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:item_masters,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price_centavos' => ['required', 'integer', 'min:0'],
            'items.*.remarks' => ['sometimes', 'string'],
        ];
    }
}
