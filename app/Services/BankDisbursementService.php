<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\Payroll\Models\PayrollRun;
use Illuminate\Support\Facades\Log;

/**
 * Generates bank disbursement files for payroll net-pay crediting.
 *
 * BDO format: CSV with columns
 *   account_number, account_name, amount, reference, pay_date
 *
 * Employees without a bank_account_no are excluded and logged.
 * All amounts are in pesos (centavos ÷ 100) with 2 decimal places.
 */
final class BankDisbursementService
{
    private const BDO_COLUMNS = [
        'account_number',
        'account_name',
        'amount',
        'reference_no',
        'pay_date',
    ];

    /**
     * Generate BDO salary crediting CSV content as a string.
     *
     * @return string Raw CSV content ready for StreamDownload.
     */
    public function generateBdo(PayrollRun $run): string
    {
        $details = $run->details()
            ->with('employee:id,employee_code,first_name,last_name,bank_account_no,bank_account_name')
            ->whereHas('employee', fn ($q) => $q->whereNotNull('bank_account_no'))
            ->orderBy('id')
            ->get();

        // Warn about employees missing bank account
        $missing = $run->details()
            ->whereHas('employee', fn ($q) => $q->whereNull('bank_account_no'))
            ->with('employee:id,employee_code,first_name,last_name')
            ->get();

        if ($missing->isNotEmpty()) {
            Log::warning('Bank disbursement: employees excluded due to missing bank account', [
                'payroll_run_id' => $run->id,
                'reference_no' => $run->reference_no,
                'excluded' => $missing->map(fn ($d) => [
                    'employee_id' => $d->employee_id,
                    'employee_code' => $d->employee?->employee_code,
                ])->toArray(),
            ]);
        }

        $output = fopen('php://temp', 'r+');

        // Header row
        fputcsv($output, self::BDO_COLUMNS);

        foreach ($details as $detail) {
            $emp = $detail->employee;

            fputcsv($output, [
                $emp?->bank_account_no ?? '',
                $emp?->bank_account_name ?? "{$emp?->last_name}, {$emp?->first_name}",
                number_format($detail->net_pay_centavos / 100, 2, '.', ''),
                $run->reference_no,
                $run->pay_date,
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }

    /**
     * Returns the count of employees that would be excluded (no bank account).
     */
    public function missingBankAccountCount(PayrollRun $run): int
    {
        return $run->details()
            ->whereHas('employee', fn ($q) => $q->whereNull('bank_account_no'))
            ->count();
    }
}
