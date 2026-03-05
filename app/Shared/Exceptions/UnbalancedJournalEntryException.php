<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * HTTP 422 — Journal entry lines do not balance (JE-001).
 *
 * Also enforced by a PostgreSQL trigger (check_journal_balance) as Layer 4.
 * This exception is thrown at Layer 3 (service) before the DB write occurs.
 */
class UnbalancedJournalEntryException extends DomainException
{
    public function __construct(float $totalDebits, float $totalCredits)
    {
        $diff = abs($totalDebits - $totalCredits);

        parent::__construct(
            message: 'Journal entry is unbalanced. Total debits: ₱'
                .number_format($totalDebits, 2)
                .', total credits: ₱'
                .number_format($totalCredits, 2)
                .' (difference: ₱'.number_format($diff, 2).').',
            errorCode: 'UNBALANCED_JOURNAL_ENTRY',
            httpStatus: 422,
            context: [
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'difference' => $diff,
            ],
        );
    }
}
