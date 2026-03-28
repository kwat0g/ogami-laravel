<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;

final class StoreSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SalesOrder::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'contact_id' => ['sometimes', 'integer', 'exists:crm_contacts,id'],
            'quotation_id' => ['sometimes', 'integer', 'exists:quotations,id'],
            'opportunity_id' => ['sometimes', 'integer', 'exists:crm_opportunities,id'],
            'requested_delivery_date' => ['sometimes', 'date'],
            'promised_delivery_date' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:item_masters,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price_centavos' => ['required', 'integer', 'min:0'],
            'items.*.remarks' => ['sometimes', 'string'],
        ];
    }
}
