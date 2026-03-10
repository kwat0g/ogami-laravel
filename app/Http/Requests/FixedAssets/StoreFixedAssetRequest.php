<?php

declare(strict_types=1);

namespace App\Http\Requests\FixedAssets;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFixedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    public function rules(): array
    {
        return [
            'category_id'               => ['required', 'integer', 'exists:fixed_asset_categories,id'],
            'department_id'             => ['nullable', 'integer', 'exists:departments,id'],
            'name'                      => ['required', 'string', 'max:200'],
            'description'               => ['nullable', 'string'],
            'serial_number'             => ['nullable', 'string', 'max:100'],
            'location'                  => ['nullable', 'string', 'max:200'],
            'acquisition_date'          => ['required', 'date'],
            'acquisition_cost_centavos' => ['required', 'integer', 'min:1'],
            'residual_value_centavos'   => ['nullable', 'integer', 'min:0'],
            'useful_life_years'         => ['nullable', 'integer', 'min:1', 'max:50'],
            'depreciation_method'       => ['nullable', 'string', 'in:straight_line,double_declining,units_of_production'],
            'purchased_from'            => ['nullable', 'string', 'max:200'],
            'purchase_invoice_ref'      => ['nullable', 'string', 'max:100'],
        ];
    }
}
