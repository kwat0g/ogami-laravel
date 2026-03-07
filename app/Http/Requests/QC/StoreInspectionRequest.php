<?php

declare(strict_types=1);

namespace App\Http\Requests\QC;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInspectionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'stage'                  => 'required|in:iqc,ipqc,oqc',
            'inspection_template_id' => 'nullable|exists:inspection_templates,id',
            'goods_receipt_id'       => 'nullable|exists:goods_receipts,id',
            'production_order_id'    => 'nullable|exists:production_orders,id',
            'item_master_id'         => 'nullable|exists:item_masters,id',
            'lot_batch_id'           => 'nullable|exists:lot_batches,id',
            'qty_inspected'          => 'required|numeric|min:0.0001',
            'inspection_date'        => 'required|date',
            'inspector_id'           => 'nullable|exists:employees,id',
            'remarks'                => 'nullable|string',
        ];
    }
}
