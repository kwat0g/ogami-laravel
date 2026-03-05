<?php

declare(strict_types=1);

namespace App\Http\Resources\Payroll;

use App\Domains\Payroll\Models\PayrollAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayrollAdjustment */
final class PayrollAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_run_id' => $this->payroll_run_id,
            'employee_id' => $this->employee_id,
            'type' => $this->type,
            'nature' => $this->nature,
            'description' => $this->description,
            'amount_centavos' => $this->amount_centavos,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
