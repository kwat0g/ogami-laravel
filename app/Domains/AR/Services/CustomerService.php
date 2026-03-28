<?php

declare(strict_types=1);

namespace App\Domains\AR\Services;

use App\Domains\AR\Models\Customer;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Traits\HasArchiveOperations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CustomerService implements ServiceContract
{
    use HasArchiveOperations;
    // ── List / Search ─────────────────────────────────────────────────────────

    /**
     * @param  array{search?: string, is_active?: bool, per_page?: int, with_portal_user?: bool}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Customer::query();

        if (! empty($filters['search'])) {
            $search = "%{$filters['search']}%";
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('tin', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['with_portal_user'])) {
            $query->with('portalUser');
        }

        // Eagerly aggregate billed/paid totals to avoid N+1 when current_outstanding is accessed
        $query
            ->withSum(
                ['invoices as billed_total' => fn ($q) => $q->whereIn('status', ['approved', 'partially_paid', 'paid'])],
                'total_amount'
            )
            ->withSum('payments as total_paid', 'amount');

        return $query
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 25);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    /**
     * @param array{
     *   name: string,
     *   tin?: string,
     *   email?: string,
     *   phone?: string,
     *   contact_person?: string,
     *   address?: string,
     *   billing_address?: string,
     *   credit_limit?: float,
     *   ar_account_id?: int,
     *   notes?: string,
     * } $data
     */
    public function create(array $data, int $userId): Customer
    {
        return Customer::create([
            ...$data,
            'created_by' => $userId,
            'is_active' => true,
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer->fresh();
    }

    // ── Archive (soft-delete) ─────────────────────────────────────────────────

    /**
     * Soft-delete a customer (move to archive).
     * Does NOT change is_active — archive != disable (Rule 2).
     * Blocks if the customer has open (unpaid) invoices.
     */
    public function archive(Customer $customer, User $user): void
    {
        $openCount = $customer->invoices()
            ->whereNotIn('status', ['paid', 'written_off', 'cancelled'])
            ->count();

        if ($openCount > 0) {
            throw new DomainException(
                "Cannot archive '{$customer->name}': {$openCount} open invoice(s) still outstanding.",
                'AR_CUSTOMER_HAS_OPEN_INVOICES',
                422
            );
        }

        $this->archiveRecord($customer, $user);
    }

    // ── Restore ────────────────────────────────────────────────────────────────

    public function restore(int $id, User $user): Customer
    {
        /** @var Customer */
        return $this->restoreRecord(Customer::class, $id, $user);
    }

    // ── Permanent Delete ───────────────────────────────────────────────────────

    public function forceDelete(int $id, User $user): void
    {
        $this->forceDeleteRecord(Customer::class, $id, $user);
    }

    // ── List Archived ──────────────────────────────────────────────────────────

    public function listArchived(int $perPage = 20, ?string $search = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->listArchivedRecords(Customer::class, $perPage, $search, ['name', 'tin', 'email']);
    }

    // ── Deactivate / Activate (status only, no archive) ────────────────────────

    public function deactivate(Customer $customer): Customer
    {
        $customer->update(['is_active' => false]);

        return $customer->fresh();
    }

    public function activate(Customer $customer): Customer
    {
        $customer->update(['is_active' => true]);

        return $customer->fresh();
    }

    // ── Client Portal Account ─────────────────────────────────────────────────

    /** Provision a client portal user account linked to this customer. */
    public function provisionPortalAccount(Customer $customer): array
    {
        return DB::transaction(function () use ($customer): array {
            $existing = User::where('client_id', $customer->id)->first();
            if ($existing) {
                throw new DomainException(
                    message: "Customer already has a portal account: {$existing->email}",
                    errorCode: 'CUSTOMER_ACCOUNT_EXISTS',
                    httpStatus: 422,
                );
            }

            if (! $customer->email) {
                throw new DomainException(
                    message: 'Customer must have an email address before creating a portal account. Please update the customer record first.',
                    errorCode: 'CUSTOMER_EMAIL_MISSING',
                    httpStatus: 422,
                );
            }

            $tempPassword = 'Client'.Str::random(8).'!';

            $user = User::create([
                'name' => $customer->contact_person ?? $customer->name,
                'email' => $customer->email,
                'password' => $tempPassword,
                'client_id' => $customer->id,
                'email_verified_at' => now(),
                'password_changed_at' => null, // force change on first login
            ]);

            $user->syncRoles(['client']);

            return [
                'user_id' => $user->id,
                'email' => $user->email,
                'password' => $tempPassword,
                'role' => 'client',
            ];
        });
    }

    /** Reset the client portal account password and unlock the account. */
    public function resetPortalAccountPassword(Customer $customer): array
    {
        return DB::transaction(function () use ($customer): array {
            $user = User::where('client_id', $customer->id)->first();
            if (! $user) {
                throw new DomainException(
                    message: 'Customer does not have a portal account yet.',
                    errorCode: 'CUSTOMER_ACCOUNT_MISSING',
                    httpStatus: 422,
                );
            }

            $tempPassword = 'Client'.Str::random(8).'!';

            $user->update([
                'password' => $tempPassword,
                'password_changed_at' => null, // force change on next login
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);

            return [
                'user_id' => $user->id,
                'email' => $user->email,
                'password' => $tempPassword,
                'role' => 'client',
            ];
        });
    }
}
