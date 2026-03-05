<?php

declare(strict_types=1);

namespace App\Domains\AR\Services;

use App\Domains\AR\Models\Customer;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CustomerService implements ServiceContract
{
    // ── List / Search ─────────────────────────────────────────────────────────

    /**
     * @param  array{search?: string, is_active?: bool, per_page?: int}  $filters
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

    // ── Archive ───────────────────────────────────────────────────────────────

    /**
     * Soft-delete and deactivate a customer.
     * Blocks if the customer has open (unpaid) invoices.
     */
    public function archive(Customer $customer): void
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

        $customer->update(['is_active' => false]);
        $customer->delete();
    }
}
