<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * HTTP 422 — Payroll computation produced a negative net pay.
 *
 * This is a hard invariant: net_pay_adjusted >= 0 must always hold.
 * When this exception is thrown, the affected employee is flagged
 * COMPUTATION_ERROR and the payroll run continues for other employees
 * (EDGE-002, DED-002).
 */
class NegativeNetPayException extends DomainException
{
    public function __construct(
        int $employeeId,
        string $employeeCode,
        float $computedNetPay,
    ) {
        parent::__construct(
            message: 'Payroll computation resulted in negative net pay (₱'
                .number_format($computedNetPay, 2)
                .") for employee {$employeeCode}. "
                .'Statutory deductions exceed gross pay. Manual review required.',
            errorCode: 'NEGATIVE_NET_PAY',
            httpStatus: 422,
            context: [
                'employee_id' => $employeeId,
                'employee_code' => $employeeCode,
                'computed_net_pay' => $computedNetPay,
            ],
        );
    }
}
