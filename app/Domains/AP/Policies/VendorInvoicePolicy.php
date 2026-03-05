<?php

declare(strict_types=1);

namespace App\Domains\AP\Policies;

use App\Domains\AP\Models\VendorInvoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Vendor Invoice Policy.
 *
 * AP-006: invoices may only be updated while in draft.
 * AP-010: approver ≠ submitter (SoD — also enforced in VendorInvoiceService).
 *
 * Permissions:
 *   vendor_invoices.view, .create, .update, .submit, .approve, .reject,
 *   .record_payment, .cancel
 */
final class VendorInvoicePolicy
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
        return $user->hasPermissionTo('vendor_invoices.view');
    }

    public function view(User $user, VendorInvoice $invoice): bool
    {
        return $user->hasPermissionTo('vendor_invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor_invoices.create');
    }

    /** AP-006: only draft invoices are editable. */
    public function update(User $user, VendorInvoice $invoice): bool
    {
        if (! $invoice->isEditable()) {
            return false; // AP-006
        }

        return $user->hasPermissionTo('vendor_invoices.update');
    }

    public function submit(User $user, VendorInvoice $invoice): bool
    {
        if (! $invoice->isDraft()) {
            return false;
        }

        return $user->hasPermissionTo('vendor_invoices.submit');
    }

    /**
     * AP-010: SoD — approver must not be the same person who submitted.
     * Service re-validates; policy provides an early gate.
     */
    public function approve(User $user, VendorInvoice $invoice): bool
    {
        if (! $invoice->isPendingApproval()) {
            return false;
        }

        if ($invoice->submitted_by === $user->id) {
            return false; // AP-010
        }

        return $user->hasPermissionTo('vendor_invoices.approve');
    }

    public function reject(User $user, VendorInvoice $invoice): bool
    {
        if (! $invoice->isPendingApproval()) {
            return false;
        }

        if ($invoice->submitted_by === $user->id) {
            return false; // AP-010
        }

        return $user->hasPermissionTo('vendor_invoices.reject');
    }

    public function recordPayment(User $user, VendorInvoice $invoice): bool
    {
        if (! in_array($invoice->status, ['approved', 'partially_paid'], true)) {
            return false;
        }

        return $user->hasPermissionTo('vendor_invoices.record_payment');
    }

    public function cancel(User $user, VendorInvoice $invoice): bool
    {
        if (! in_array($invoice->status, ['draft', 'pending_approval'], true)) {
            return false;
        }

        return $user->hasPermissionTo('vendor_invoices.cancel');
    }
}
