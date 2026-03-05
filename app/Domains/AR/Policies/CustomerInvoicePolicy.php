<?php

declare(strict_types=1);

namespace App\Domains\AR\Policies;

use App\Domains\AR\Models\CustomerInvoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Customer Invoice Policy.
 *
 * AR-002: credit limit override requires a specific permission.
 * AR-006: bad debt write-off restricted to Accounting Managers.
 * SoD:   approver ≠ creator (enforced both here and in service).
 *
 * Permissions:
 *   customer_invoices.view | .create | .update | .approve | .cancel
 *   customer_invoices.receive_payment | .write_off | .override_credit
 */
final class CustomerInvoicePolicy
{
    use HandlesAuthorization;

    /** Admin bypass — admin role has unconditional access to all resources. */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('customer_invoices.view');
    }

    public function view(User $user, CustomerInvoice $invoice): bool
    {
        return $user->hasPermissionTo('customer_invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('customer_invoices.create');
    }

    /** AR-006: only draft invoices are editable. */
    public function update(User $user, CustomerInvoice $invoice): bool
    {
        if (! $invoice->isEditable()) {
            return false;
        }

        return $user->hasPermissionTo('customer_invoices.update');
    }

    public function cancel(User $user, CustomerInvoice $invoice): bool
    {
        if ($invoice->status !== 'draft') {
            return false;
        }

        return $user->hasPermissionTo('customer_invoices.cancel');
    }

    /**
     * SoD: approver must not be the same person who created the invoice.
     */
    public function approve(User $user, CustomerInvoice $invoice): bool
    {
        if ($invoice->status !== 'draft') {
            return false;
        }

        if ($invoice->created_by === $user->id) {
            return false; // SoD
        }

        return $user->hasPermissionTo('customer_invoices.approve');
    }

    public function receivePayment(User $user, CustomerInvoice $invoice): bool
    {
        if (! in_array($invoice->status, ['approved', 'partially_paid'], true)) {
            return false;
        }

        return $user->hasPermissionTo('customer_invoices.receive_payment');
    }

    /**
     * AR-006: write-off restricted to Accounting Managers.
     */
    public function writeOff(User $user, CustomerInvoice $invoice): bool
    {
        if (! in_array($invoice->status, ['approved', 'partially_paid'], true)) {
            return false;
        }

        return $user->hasPermissionTo('customer_invoices.write_off');
    }

    /** AR-002: credit limit override permission. */
    public function overrideCredit(User $user): bool
    {
        return $user->hasPermissionTo('customer_invoices.override_credit');
    }
}
