<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/** HTTP 422 — Customer invoice would exceed the approved credit limit (AR-001). */
class CreditLimitExceededException extends DomainException
{
    public function __construct(
        string $customerName,
        float $currentOutstanding,
        float $creditLimit,
        float $invoiceAmount,
    ) {
        parent::__construct(
            message: 'Invoice of ₱'.number_format($invoiceAmount, 2)
                .' would exceed the credit limit of ₱'.number_format($creditLimit, 2)
                ." for customer '{$customerName}'. "
                .'Current outstanding: ₱'.number_format($currentOutstanding, 2).'.',
            errorCode: 'CREDIT_LIMIT_EXCEEDED',
            httpStatus: 422,
            context: [
                'customer_name' => $customerName,
                'current_outstanding' => $currentOutstanding,
                'credit_limit' => $creditLimit,
                'invoice_amount' => $invoiceAmount,
            ],
        );
    }
}
