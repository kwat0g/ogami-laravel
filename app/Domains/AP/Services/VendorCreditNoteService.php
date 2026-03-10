<?php

declare(strict_types=1);

namespace App\Domains\AP\Services;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\AP\Models\Vendor;
use App\Domains\AP\Models\VendorCreditNote;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Manages AP vendor credit/debit notes.
 *
 * Credit note (vendor issued)  — Dr AP Payable / Cr Purchase Returns → reduces what we owe vendor
 * Debit note (we issue vendor) — Dr Vendor Claim Receivable / Cr AP Payable → vendor owes us
 */
final class VendorCreditNoteService implements ServiceContract
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(Vendor $vendor, array $data, User $actor): VendorCreditNote
    {
        return DB::transaction(function () use ($vendor, $data, $actor): VendorCreditNote {
            $prefix = $data['note_type'] === 'debit' ? 'DN-AP' : 'CN-AP';
            $seq    = DB::selectOne('SELECT NEXTVAL(\'vendor_credit_note_seq\') AS val');
            $num    = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);
            $ref    = "{$prefix}-" . now()->format('Y-m') . '-' . $num;

            return VendorCreditNote::create([
                'cn_reference'      => $ref,
                'vendor_id'         => $vendor->id,
                'vendor_invoice_id' => $data['vendor_invoice_id'] ?? null,
                'note_type'         => $data['note_type'] ?? 'credit',
                'note_date'         => $data['note_date'],
                'amount_centavos'   => $data['amount_centavos'],
                'reason'            => $data['reason'],
                'ap_account_id'     => $data['ap_account_id'],
                'status'            => 'draft',
                'created_by_id'     => $actor->id,
            ]);
        });
    }

    /**
     * Post the credit/debit note to the GL and mark as posted.
     *
     * Credit note: Dr AP Payable / Cr Purchase Returns (expense contra account)
     * Debit note:  Dr Expense Correction / Cr AP Payable
     */
    public function post(VendorCreditNote $note, User $actor): VendorCreditNote
    {
        if ($note->status !== 'draft') {
            throw new DomainException(
                message: "Credit note is already '{$note->status}'.",
                errorCode: 'CN_ALREADY_POSTED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($note): VendorCreditNote {
            $date         = $note->note_date->toDateString();
            $amount       = $note->amount_centavos / 100;
            $fiscalPeriod = $this->ensureFiscalPeriod($date);
            $systemUserId = $this->systemUserId();

            // Purchase returns account — default COA code 5010 or use ap_account's contra
            $returnsAccountId = $this->accountIdByCode('5010');

            $je = JournalEntry::create([
                'date'             => $date,
                'description'      => "Vendor {$note->note_type} note #{$note->cn_reference}",
                'source_type'      => 'ap',
                'source_id'        => $note->id,
                'status'           => 'draft',
                'fiscal_period_id' => $fiscalPeriod->id,
                'created_by'       => $systemUserId,
                'je_number'        => null,
            ]);

            if ($note->note_type === 'credit') {
                // Dr AP Payable / Cr Purchase Returns
                $je->lines()->create(['account_id' => $note->ap_account_id, 'debit' => $amount, 'credit' => null]);
                $je->lines()->create(['account_id' => $returnsAccountId,    'debit' => null,    'credit' => $amount]);
            } else {
                // Debit note: Dr Expense Correction / Cr AP Payable
                $je->lines()->create(['account_id' => $returnsAccountId,    'debit' => $amount, 'credit' => null]);
                $je->lines()->create(['account_id' => $note->ap_account_id, 'debit' => null,    'credit' => $amount]);
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
                'AP_ACCOUNT_NOT_FOUND',
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
