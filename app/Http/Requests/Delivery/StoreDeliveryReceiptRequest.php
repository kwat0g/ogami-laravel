<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDeliveryReceiptRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'vendor_id'              => 'nullable|exists:vendors,id',
            'customer_id'            => 'nullable|exists:customers,id',
            'direction'              => 'required|in:inbound,outbound',
            'receipt_date'           => 'required|date',
            'remarks'                => 'nullable|string',
            'received_by_id'         => 'nullable|exists:users,id',
            'items'                  => 'array',
            'items.*.item_master_id'   => 'required|exists:item_masters,id',
            'items.*.quantity_expected'=> 'required|numeric|min:0',
            'items.*.quantity_received'=> 'required|numeric|min:0',
            'items.*.unit_of_measure'  => 'nullable|string|max:30',
            'items.*.lot_batch_number' => 'nullable|string|max:100',
            'items.*.remarks'          => 'nullable|string',
        ];
    }
}
