<?php

declare(strict_types=1);

namespace App\Domains\AR\Services;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\AR\Models\Customer;
use App\Domains\AR\Models\CustomerCreditNote;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Manages AR customer credit/debit notes.
 *
 * Credit note (we issue to customer — reduces what they owe):
 *   Dr Sales Returns / Cr AR Receivable
 *
 * Debit note (we issue to customer — increases what they owe):
 *   Dr AR Receivable / Cr Revenue
 */
final class CustomerCreditNoteService implements ServiceContract
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(Customer $customer, array $data, User $actor): CustomerCreditNote
    {
        return DB::transaction(function () use ($customer, $data, $actor): CustomerCreditNote {
            $prefix = $data['note_type'] === 'debit' ? 'DN-AR' : 'CN-AR';
            $seq    = DB::selectOne('SELECT NEXTVAL(\'customer_credit_note_seq\') AS val');
            $num    = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);
            $ref    = "{$prefix}-" . now()->format('Y-m') . '-' . $num;

            return CustomerCreditNote::create([
                'cn_reference'        => $ref,
                'customer_id'         => $customer->id,
                'customer_invoice_id' => $data['customer_invoice_id'] ?? null,
                'note_type'           => $data['note_type'] ?? 'credit',
                'note_date'           => $data['note_date'],
                'amount_centavos'     => $data['amount_centavos'],
                'reason'              => $data['reason'],
                'ar_account_id'       => $data['ar_account_id'],
                'status'              => 'draft',
                'created_by_id'       => $actor->id,
            ]);
        });
    }

    /**
     * Post the credit/debit note to the GL and mark as posted.
     */
    public function post(CustomerCreditNote $note, User $actor): CustomerCreditNote
    {
        if ($note->status !== 'draft') {
            throw new DomainException(
                message: "Credit note is already '{$note->status}'.",
                errorCode: 'CN_ALREADY_POSTED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($note): CustomerCreditNote {
            $date         = $note->note_date->toDateString();
            $amount       = $note->amount_centavos / 100;
            $fiscalPeriod = $this->ensureFiscalPeriod($date);
            $systemUserId = $this->systemUserId();

            // Sales returns account — default COA 4010 (Sales Returns & Allowances)
            $returnsAccountId = $this->accountIdByCode('4010');

            $je = JournalEntry::create([
                'date'             => $date,
                'description'      => "Customer {$note->note_type} note #{$note->cn_reference}",
                'source_type'      => 'ar',
                'source_id'        => $note->id,
                'status'           => 'draft',
                'fiscal_period_id' => $fiscalPeriod->id,
                'created_by'       => $systemUserId,
                'je_number'        => null,
            ]);

            if ($note->note_type === 'credit') {
                // Dr Sales Returns / Cr AR Receivable
                $je->lines()->create(['account_id' => $returnsAccountId, 'debit' => $amount, 'credit' => null]);
                $je->lines()->create(['account_id' => $note->ar_account_id,  'debit' => null, 'credit' => $amount]);
            } else {
                // Debit note: Dr AR Receivable / Cr Revenue
                $je->lines()->create(['account_id' => $note->ar_account_id,  'debit' => $amount, 'credit' => null]);
                $je->lines()->create(['account_id' => $returnsAccountId, 'debit' => null, 'credit' => $amount]);
            }

            $je->update([
                'status'    => 'posted',
                'je_number' => "JE-CN-{$note->id}",
                'posted_by' => null,
                'posted_at' => now(),
            ]);

            $note->update([
                'status'           => 'posted',
                'journal_entry_id' => $je->id,
                'posted_at'        => now(),
            ]);

            return $note->refresh();
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function accountIdByCode(string $code): int
    {
        $id = \App\Domains\Accounting\Models\ChartOfAccount::where('account_code', $code)->value('id');

        if ($id === null) {
            throw new DomainException(
                "Chart of account '{$code}' not found.",
                'AR_ACCOUNT_NOT_FOUND',
                422,
            );
        }

        return $id;
    }

    private function ensureFiscalPeriod(string $date): FiscalPeriod
    {
        $period = FiscalPeriod::whereDate('date_from', '<=', $date)
            ->whereDate('date_to', '>=', $date)
            ->orderBy('date_from', 'desc')
            ->first();

        if ($period !== null) {
            return $period;
        }

        $carbon = \Carbon\Carbon::parse($date);

        return FiscalPeriod::firstOrCreate(
            ['name' => $carbon->format('M Y')],
            ['date_from' => $carbon->startOfMonth()->toDateString(), 'date_to' => $carbon->endOfMonth()->toDateString(), 'status' => 'open'],
        );
    }

    private function systemUserId(): int
    {
        return \App\Models\User::where('email', 'system-test@ogami.test')->value('id')
            ?? \App\Models\User::value('id')
            ?? 1;
    }
}
