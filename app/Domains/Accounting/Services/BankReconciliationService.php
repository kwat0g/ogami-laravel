<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\BankAccount;
use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankTransaction;
use App\Domains\Accounting\Models\JournalEntryLine;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\SodViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Bank Reconciliation Service — GL-006
 *
 * Manages the full lifecycle:
 *   DRAFT → matching transactions → CERTIFIED
 *
 * SoD enforced at two layers:
 *   1. Service: throws SodViolationException if certifier == drafter
 *   2. DB CHECK: `certified_by != created_by`
 */
final class BankReconciliationService implements ServiceContract
{
    // ── Write operations ─────────────────────────────────────────────────────

    /**
     * Create a new draft reconciliation for a bank account.
     */
    public function create(array $data): BankReconciliation
    {
        $bankAccount = BankAccount::findOrFail($data['bank_account_id']);

        if (! $bankAccount->is_active) {
            throw new DomainException(
                message: "Bank account '{$bankAccount->name}' is inactive and cannot be reconciled.",
                errorCode: 'BANK_RECON_ACCOUNT_INACTIVE',
                httpStatus: 422,
            );
        }

        // Prevent overlapping open reconciliations for the same account + period
        $overlap = BankReconciliation::where('bank_account_id', $bankAccount->id)
            ->where('status', 'draft')
            ->where(function ($q) use ($data): void {
                $q->whereBetween('period_from', [$data['period_from'], $data['period_to']])
                    ->orWhereBetween('period_to', [$data['period_from'], $data['period_to']]);
            })
            ->exists();

        if ($overlap) {
            throw new DomainException(
                message: 'An open reconciliation already exists for this bank account in the overlapping period.',
                errorCode: 'BANK_RECON_OVERLAP',
                httpStatus: 422,
            );
        }

        return BankReconciliation::create([
            'bank_account_id' => $bankAccount->id,
            'period_from' => $data['period_from'],
            'period_to' => $data['period_to'],
            'opening_balance' => $data['opening_balance'] ?? 0,
            'closing_balance' => $data['closing_balance'] ?? 0,
            'status' => 'draft',
            'created_by' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Bulk-import bank statement lines into a reconciliation.
     * All imported lines start as 'unmatched'.
     *
     * @param array<int, array{
     *     transaction_date: string,
     *     description: string,
     *     amount: float,
     *     transaction_type: string,
     *     reference_number?: string
     * }> $transactions
     */
    public function importStatement(BankReconciliation $reconciliation, array $transactions): int
    {
        $this->assertDraft($reconciliation);

        $rows = array_map(fn (array $tx) => [
            'bank_account_id' => $reconciliation->bank_account_id,
            'bank_reconciliation_id' => $reconciliation->id,
            'transaction_date' => $tx['transaction_date'],
            'description' => $tx['description'],
            'amount' => $tx['amount'],
            'transaction_type' => $tx['transaction_type'],
            'reference_number' => $tx['reference_number'] ?? null,
            'status' => 'unmatched',
            'created_at' => now(),
            'updated_at' => now(),
        ], $transactions);

        DB::table('bank_transactions')->insert($rows);

        return count($rows);
    }

    /**
     * Match a bank transaction to a GL journal entry line.
     *
     * @throws DomainException if the transaction is already matched or the JE line
     *                         belongs to a different account
     */
    public function matchTransaction(BankTransaction $bankTx, JournalEntryLine $jeLine): BankTransaction
    {
        $this->assertReconciliationDraft($bankTx);

        if ($bankTx->isMatched() || $bankTx->isReconciled()) {
            throw new DomainException(
                message: "Bank transaction #{$bankTx->id} is already {$bankTx->status}.",
                errorCode: 'BANK_TXN_ALREADY_MATCHED',
                httpStatus: 422,
            );
        }

        if ($jeLine->account_id !== $bankTx->bankAccount->account_id) {
            throw new DomainException(
                message: "Journal entry line #{$jeLine->id} does not belong to the GL account linked to this bank account.",
                errorCode: 'BANK_TXN_ACCOUNT_MISMATCH',
                httpStatus: 422,
            );
        }

        // Ensure the JE line is not already matched to another bank transaction
        $alreadyMatched = BankTransaction::where('journal_entry_line_id', $jeLine->id)
            ->where('id', '!=', $bankTx->id)
            ->whereIn('status', ['matched', 'reconciled'])
            ->exists();

        if ($alreadyMatched) {
            throw new DomainException(
                message: "Journal entry line #{$jeLine->id} is already matched to another bank transaction.",
                errorCode: 'BANK_TXN_JE_LINE_ALREADY_MATCHED',
                httpStatus: 422,
            );
        }

        $bankTx->update([
            'status' => 'matched',
            'journal_entry_line_id' => $jeLine->id,
        ]);

        return $bankTx->fresh();
    }

    /**
     * Unmatch a previously matched bank transaction, returning it to 'unmatched'.
     */
    public function unmatchTransaction(BankTransaction $bankTx): BankTransaction
    {
        $this->assertReconciliationDraft($bankTx);

        if (! $bankTx->isMatched()) {
            throw new DomainException(
                message: "Bank transaction #{$bankTx->id} is not in 'matched' status and cannot be unmatched.",
                errorCode: 'BANK_TXN_NOT_MATCHED',
                httpStatus: 422,
            );
        }

        $bankTx->update([
            'status' => 'unmatched',
            'journal_entry_line_id' => null,
        ]);

        return $bankTx->fresh();
    }

    /**
     * Certify a reconciliation — marks it as certified and all matched
     * transactions as 'reconciled'.
     *
     * SoD: the certifier must be different from the drafter.
     *
     * @throws SodViolationException if certifier == drafter
     * @throws DomainException if there are still unmatched transactions
     */
    public function certify(BankReconciliation $reconciliation, User $certifier): BankReconciliation
    {
        $this->assertDraft($reconciliation);

        // SoD layer 1: service-level check
        if ($certifier->id === $reconciliation->created_by) {
            throw new SodViolationException(
                processName: 'bank_reconciliation',
                conflictingAction: 'certify',
            );
        }

        $unmatchedCount = $reconciliation->unmatchedCount();
        if ($unmatchedCount > 0) {
            throw new DomainException(
                message: "Cannot certify: {$unmatchedCount} bank transaction(s) are still unmatched.",
                errorCode: 'BANK_RECON_UNMATCHED_TRANSACTIONS',
                httpStatus: 422,
                context: ['unmatched_count' => $unmatchedCount],
            );
        }

        DB::transaction(function () use ($reconciliation, $certifier): void {
            // Mark all matched transactions as reconciled
            $reconciliation->transactions()
                ->where('status', 'matched')
                ->update(['status' => 'reconciled', 'updated_at' => now()]);

            // Certify the reconciliation header
            $reconciliation->update([
                'status' => 'certified',
                'certified_by' => $certifier->id,
                'certified_at' => now(),
            ]);
        });

        return $reconciliation->fresh();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function assertDraft(BankReconciliation $reconciliation): void
    {
        if (! $reconciliation->isDraft()) {
            throw new DomainException(
                message: "Reconciliation #{$reconciliation->id} is already {$reconciliation->status} and cannot be modified.",
                errorCode: 'BANK_RECON_NOT_DRAFT',
                httpStatus: 422,
            );
        }
    }

    private function assertReconciliationDraft(BankTransaction $bankTx): void
    {
        if ($bankTx->bank_reconciliation_id === null) {
            throw new DomainException(
                message: "Bank transaction #{$bankTx->id} is not associated with a reconciliation.",
                errorCode: 'BANK_TXN_NO_RECONCILIATION',
                httpStatus: 422,
            );
        }

        $reconciliation = $bankTx->reconciliation;

        if ($reconciliation === null || ! $reconciliation->isDraft()) {
            throw new DomainException(
                message: "The reconciliation associated with bank transaction #{$bankTx->id} is not in draft status.",
                errorCode: 'BANK_RECON_NOT_DRAFT',
                httpStatus: 422,
            );
        }
    }
}
