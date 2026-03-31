<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * CHAIN-DR-001: Outbound delivery receipts must reference an upstream document
 * (sales_order_id or delivery_schedule_id) to maintain the SO → DR chain.
 * Inbound receipts (from vendors) remain unrestricted.
 */
final class StoreDeliveryReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'vendor_id' => 'nullable|exists:vendors,id',
            'customer_id' => 'nullable|exists:customers,id',
            'sales_order_id' => 'nullable|integer|exists:sales_orders,id',
            'delivery_schedule_id' => 'nullable|integer|exists:delivery_schedules,id',
            'direction' => 'required|in:inbound,outbound',
            'receipt_date' => 'required|date',
            'remarks' => 'nullable|string',
            'received_by_id' => 'nullable|exists:users,id',
            'items' => 'array',
            'items.*.item_master_id' => 'required|exists:item_masters,id',
            'items.*.quantity_expected' => 'required|numeric|min:0',
            'items.*.quantity_received' => 'required|numeric|min:0',
            'items.*.unit_of_measure' => 'nullable|string|max:30',
            'items.*.lot_batch_number' => 'nullable|string|max:100',
            'items.*.remarks' => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $direction = $this->input('direction');

            // CHAIN-DR-001: Outbound DRs must reference SO or delivery schedule.
            if ($direction === 'outbound') {
                $hasSo = ! empty($this->input('sales_order_id'));
                $hasDs = ! empty($this->input('delivery_schedule_id'));

                if (! $hasSo && ! $hasDs) {
                    $v->errors()->add(
                        'sales_order_id',
                        'Outbound delivery receipts must reference a Sales Order or Delivery Schedule. (CHAIN-DR-001)'
                    );
                }

                if (empty($this->input('customer_id'))) {
                    $v->errors()->add(
                        'customer_id',
                        'Outbound delivery receipts must specify a customer.'
                    );
                }
            }
        });
    }
}
