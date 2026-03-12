<?php

declare(strict_types=1);

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * VendorItemImport — parse CSV/Excel file into validated rows for VendorItemService::importRows().
 *
 * Expected columns: item_code, item_name, description, unit_of_measure, unit_price, is_active
 * - unit_price is in PHP pesos (float), e.g. 250.00 → will be converted to 25000 centavos by the service
 * - is_active is optional, defaults to true
 */
final class VendorItemImport implements ToArray, WithHeadingRow, WithValidation
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function array(array $rows): void
    {
        $this->rows = $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'item_code'      => ['required', 'string', 'max:50'],
            'item_name'      => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'unit_of_measure' => ['nullable', 'string', 'max:20'],
            'unit_price'     => ['required', 'numeric', 'min:0'],
            'is_active'      => ['nullable'],
        ];
    }
}
