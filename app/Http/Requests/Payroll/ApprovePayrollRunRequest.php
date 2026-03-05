<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use App\Domains\Payroll\Models\PayrollRun;
use Illuminate\Foundation\Http\FormRequest;

final class ApprovePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PayrollRun $run */
        $run = $this->route('payrollRun');

        return $this->user()->can('payroll.approve') === true
            && (int) $this->user()->id !== $run->created_by;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
