<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * CHAIN-PO-001: source_type must match provided FK.
 *   - client_order → client_order_id required
 *   - delivery_schedule → delivery_schedule_id required
 *   - manual → no upstream FK required
 */
final class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'source_type' => ['sometimes', 'string', 'in:manual,client_order,delivery_schedule,sales_order,rework,force_production,replenishment'],
            'client_order_id' => ['nullable', 'integer', 'exists:client_orders,id'],
            'delivery_schedule_id' => ['nullable', 'integer', 'exists:delivery_schedules,id'],
            'sales_order_id' => ['nullable', 'integer', 'exists:sales_orders,id'],
            'product_item_id' => ['required', 'integer', 'exists:item_masters,id'],
            'bom_id' => ['required', 'integer', 'exists:bill_of_materials,id'],
            'qty_required' => ['required', 'numeric', 'min:0.0001'],
            'target_start_date' => ['required', 'date'],
            'target_end_date' => ['required', 'date', 'after_or_equal:target_start_date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $sourceType = $this->input('source_type', 'manual');

            // CHAIN-PO-001: source_type must have matching FK
            if ($sourceType === 'client_order' && empty($this->input('client_order_id'))) {
                $v->errors()->add(
                    'client_order_id',
                    'client_order_id is required when source_type is client_order. (CHAIN-PO-001)'
                );
            }

            if ($sourceType === 'delivery_schedule' && empty($this->input('delivery_schedule_id'))) {
                $v->errors()->add(
                    'delivery_schedule_id',
                    'delivery_schedule_id is required when source_type is delivery_schedule. (CHAIN-PO-001)'
                );
            }

            if ($sourceType === 'sales_order' && empty($this->input('sales_order_id'))) {
                $v->errors()->add(
                    'sales_order_id',
                    'sales_order_id is required when source_type is sales_order. (CHAIN-PO-001)'
                );
            }
        });
    }
}
