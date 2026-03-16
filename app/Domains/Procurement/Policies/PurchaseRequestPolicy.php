<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Policies;

use App\Domains\Procurement\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * PurchaseRequestPolicy — 5-stage SoD approval chain.
 *
 * Permissions (registered in RolePermissionSeeder):
 *   procurement.purchase-request.view
 *   procurement.purchase-request.create
 *   procurement.purchase-request.note      (Head)
 *   procurement.purchase-request.check     (Manager)
 *   procurement.purchase-request.review    (Officer)
 *   procurement.purchase-request.budget-check (Accounting Officer)
 *   approvals.vp.approve                   (VP — shared with loans)
 */
final class PurchaseRequestPolicy
{
    use HandlesAuthorization;

    /** Admin bypass. */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.view');
    }

    public function view(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.create');
    }

    public function update(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.create')
            && in_array($pr->status, ['draft', 'returned'], true);
    }

    public function submit(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.create')
            && in_array($pr->status, ['draft', 'returned'], true)
            && $pr->requested_by_id === $user->id;
    }

    /** Head notes the PR — SOD-011: noter cannot be the submitter. */
    public function note(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.note')
            && $pr->status === 'submitted'
            && $user->id !== $pr->submitted_by_id;
    }

    /** Manager checks — SOD-012: checker cannot be the noter. */
    public function check(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.check')
            && $pr->status === 'noted'
            && $user->id !== $pr->noted_by_id;
    }

    /** Officer reviews — SOD-013: reviewer cannot be the checker. */
    public function review(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.review')
            && $pr->status === 'checked'
            && $user->id !== $pr->checked_by_id;
    }

    /** Accounting Officer budget-checks the reviewed PR. */
    public function budgetCheck(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.budget-check')
            && $pr->status === 'reviewed'
            && $user->id !== $pr->reviewed_by_id;
    }

    /** Accounting Officer returns the PR for revision (requester must redo from draft). */
    public function returnForRevision(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.budget-check')
            && $pr->status === 'reviewed';
    }

    /** VP final approval — SOD-014: VP cannot be the budget checker. */
    public function vpApprove(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('approvals.vp.approve')
            && $pr->status === 'budget_checked'
            && $user->id !== $pr->budget_checked_by_id;
    }

    /** Reject — allowed by the actor responsible for the current stage. */
    public function reject(User $user, PurchaseRequest $pr): bool
    {
        return match ($pr->status) {
            'submitted' => $user->hasPermissionTo('procurement.purchase-request.note'),
            'noted' => $user->hasPermissionTo('procurement.purchase-request.check'),
            'checked' => $user->hasPermissionTo('procurement.purchase-request.review'),
            'reviewed' => $user->hasPermissionTo('procurement.purchase-request.budget-check'),
            'budget_checked' => $user->hasPermissionTo('approvals.vp.approve'),
            default => false,
        };
    }

    public function cancel(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.create')
            && in_array($pr->status, ['draft', 'submitted', 'returned'], true)
            && $pr->requested_by_id === $user->id;
    }
}
