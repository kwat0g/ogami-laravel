<?php

declare(strict_types=1);

namespace App\Http\Requests\Mold;

use Illuminate\Foundation\Http\FormRequest;

final class LogMoldShotsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'shot_count'          => 'required|integer|min:1',
            'production_order_id' => 'nullable|exists:production_orders,id',
            'operator_id'         => 'nullable|exists:users,id',
            'log_date'            => 'required|date|before_or_equal:today',
            'remarks'             => 'nullable|string',
        ];
    }
}
