<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

final class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'delivery_receipt_id' => 'nullable|exists:delivery_receipts,id',
            'carrier' => 'nullable|string|max:200',
            'tracking_number' => 'nullable|string|max:200',
            'shipped_at' => 'nullable|date',
            'estimated_arrival' => 'nullable|date',
            'actual_arrival' => 'nullable|date',
            'status' => 'in:pending,in_transit,delivered,returned',
            'notes' => 'nullable|string',
        ];
    }
}
