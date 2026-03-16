<?php

declare(strict_types=1);

namespace App\Http\Requests\FixedAssets;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFixedAssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:fixed_asset_categories,name'],
            'code_prefix' => ['required', 'string', 'max:10'],
            'default_useful_life_years' => ['required', 'integer', 'min:1', 'max:50'],
            'default_depreciation_method' => ['required', 'string', 'in:straight_line,double_declining,units_of_production'],
            'gl_asset_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'gl_depreciation_expense_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'gl_accumulated_depreciation_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ];
    }
}
