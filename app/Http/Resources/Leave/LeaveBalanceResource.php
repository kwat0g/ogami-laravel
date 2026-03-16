<?php

declare(strict_types=1);

namespace App\Http\Resources\Leave;

use App\Domains\Leave\Models\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveBalance
 */
final class LeaveBalanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var LeaveBalance $lb */
        $lb = $this->resource;

        return [
            'id' => $lb->id,
            'employee_id' => $lb->employee_id,
            'leave_type_id' => $lb->leave_type_id,
            'leave_type' => $this->whenLoaded('leaveType', fn () => [
                'id' => $lb->leaveType->id,
                'code' => $lb->leaveType->code,
                'name' => $lb->leaveType->name,
            ]),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $lb->employee->id,
                'employee_code' => $lb->employee->employee_code,
                'full_name' => $lb->employee->full_name,
            ]),
            'year' => $lb->year,
            'opening_balance' => $lb->opening_balance,
            'accrued' => $lb->accrued,
            'adjusted' => $lb->adjusted,
            'used' => $lb->used,
            'monetized' => $lb->monetized,
            'balance' => $lb->balance,
        ];
    }
}
