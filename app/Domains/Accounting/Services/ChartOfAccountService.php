<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Traits\HasArchiveOperations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Chart of Accounts Service — enforces COA-001 through COA-006.
 *
 * COA-001: code uniqueness → DB unique constraint; service validates on update.
 * COA-002: only leaf nodes can be posted to → LeafAccountRule in request.
 * COA-003: system accounts protected → Policy + service guard here.
 * COA-004: normal_balance immutable after first posted JE → guard in update().
 * COA-005: archiving with non-zero balance blocked → guard in archive().
 * COA-006: max 5 hierarchy levels → enforced in createAccount().
 */
final class ChartOfAccountService implements ServiceContract
{
    use HasArchiveOperations;
    /** Maximum hierarchy depth allowed (COA-006). */
    private const MAX_DEPTH = 5;

    // ── Write operations ─────────────────────────────────────────────────────

    public function createAccount(array $data): ChartOfAccount
    {
        // COA-006: depth check
        if (isset($data['parent_id']) && $data['parent_id'] !== null) {
            $parent = ChartOfAccount::findOrFail($data['parent_id']);
            $proposedDepth = $parent->depth() + 1;
            if ($proposedDepth > self::MAX_DEPTH) {
                throw new DomainException(
                    message: 'Account hierarchy cannot exceed '.self::MAX_DEPTH." levels. The selected parent is already at level {$parent->depth()}. (COA-006)",
                    errorCode: 'COA_MAX_DEPTH_EXCEEDED',
                    httpStatus: 422,
                    context: ['parent_depth' => $parent->depth(), 'max_depth' => self::MAX_DEPTH],
                );
            }
        }

        return ChartOfAccount::create($data);
    }

    public function updateAccount(ChartOfAccount $account, array $data): ChartOfAccount
    {
        // COA-003: system accounts cannot be renamed/modified structurally
        if ($account->is_system) {
            // Allow description updates but not code/name/type changes
            foreach (['code', 'name', 'account_type', 'normal_balance', 'is_system'] as $protected) {
                if (isset($data[$protected]) && $data[$protected] != $account->{$protected}) {
                    throw new DomainException(
                        message: "System account '{$account->code}' cannot have its {$protected} modified. (COA-003)",
                        errorCode: 'COA_SYSTEM_ACCOUNT_PROTECTED',
                        httpStatus: 422,
                        context: ['account_code' => $account->code, 'field' => $protected],
                    );
                }
            }
        }

        // COA-004: normal_balance cannot change after first posted JE line
        if (isset($data['normal_balance'])
            && $data['normal_balance'] !== $account->normal_balance
            && $account->hasPostedLines()) {
            throw new DomainException(
                message: "The normal_balance of account '{$account->code}' cannot be changed after journal entries have been posted to it. (COA-004)",
                errorCode: 'COA_NORMAL_BALANCE_LOCKED',
                httpStatus: 422,
                context: ['account_code' => $account->code],
            );
        }

        $account->update($data);

        return $account->fresh();
    }

    /**
     * Archive (soft-delete) an account.
     * Does NOT change is_active — archive != disable (Rule 2).
     * COA-003: system accounts cannot be archived.
     * COA-005: balance must be zero before archiving.
     */
    public function archiveAccount(ChartOfAccount $account, ?User $user = null): void
    {
        if ($account->is_system) {
            throw new DomainException(
                message: "System account '{$account->code}' ({$account->name}) cannot be archived. It is required for automatic journal postings. (COA-003)",
                errorCode: 'COA_SYSTEM_ACCOUNT_PROTECTED',
                httpStatus: 422,
                context: ['account_code' => $account->code],
            );
        }

        // COA-005: reject if non-zero balance
        $balance = $this->computeBalance($account);
        if (abs($balance) > 0.005) {
            throw new DomainException(
                message: "Account '{$account->code}' ({$account->name}) cannot be archived because it has a non-zero balance of ₱".number_format($balance, 2).'. Transfer the balance first. (COA-005)',
                errorCode: 'COA_NON_ZERO_BALANCE',
                httpStatus: 422,
                context: ['account_code' => $account->code, 'balance' => $balance],
            );
        }

        DB::transaction(function () use ($account): void {
            $account->delete(); // soft-delete via SoftDeletes trait
        });
    }

    // ── Restore ────────────────────────────────────────────────────────────────

    public function restoreAccount(int $id, User $user): ChartOfAccount
    {
        /** @var ChartOfAccount */
        return $this->restoreRecord(ChartOfAccount::class, $id, $user);
    }

    // ── Permanent Delete ───────────────────────────────────────────────────────

    public function forceDeleteAccount(int $id, User $user): void
    {
        $this->forceDeleteRecord(ChartOfAccount::class, $id, $user);
    }

    // ── List Archived ──────────────────────────────────────────────────────────

    public function listArchived(int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return $this->listArchivedRecords(ChartOfAccount::class, $perPage, $search, ['code', 'name']);
    }

    // ── Deactivate / Activate (status only, no archive) ────────────────────────

    public function deactivateAccount(ChartOfAccount $account): ChartOfAccount
    {
        $account->update(['is_active' => false]);

        return $account->fresh();
    }

    public function activateAccount(ChartOfAccount $account): ChartOfAccount
    {
        $account->update(['is_active' => true]);

        return $account->fresh();
    }

    // ── Reads ────────────────────────────────────────────────────────────────

    /**
     * Returns the running balance for a leaf account from posted JE lines.
     * Assets/COGS/OPEX are debit-normal; Liability/Equity/Revenue/Tax are credit-normal.
     */
    public function computeBalance(ChartOfAccount $account): float
    {
        $row = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('jel.account_id', $account->id)
            ->where('je.status', 'posted')
            ->selectRaw('COALESCE(SUM(jel.debit),0) AS total_debits, COALESCE(SUM(jel.credit),0) AS total_credits')
            ->first();

        $debits = (float) ($row->total_debits ?? 0);
        $credits = (float) ($row->total_credits ?? 0);

        // For debit-normal accounts, balance = debits − credits. Invert for credit-normal.
        return in_array($account->account_type, ['ASSET', 'COGS', 'OPEX'], true)
            ? $debits - $credits
            : $credits - $debits;
    }

    /**
     * Returns the full COA tree as a nested collection (for tree-view rendering).
     */
    public function treeFor(?int $parentId = null): Collection
    {
        return ChartOfAccount::where('parent_id', $parentId)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get()
            ->map(function (ChartOfAccount $account) {
                $account->setAttribute('children_tree', $this->treeFor($account->id));

                return $account;
            });
    }
}
