<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Policies;

use App\Domains\HR\Models\Department;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * PurchaseRequestPolicy — 4-stage SoD approval chain.
 *
 * Workflow: draft → pending_review → reviewed → budget_verified → approved
 *
 * Permissions (registered in RolePermissionSeeder):
 *   procurement.purchase-request.view
 *   procurement.purchase-request.create
 *   procurement.purchase-request.create-dept  (Dept Head — own dept only)
 *   procurement.purchase-request.review       (Purchasing Officer)
 *   procurement.purchase-request.budget-check (Accounting Officer)
 *   approvals.vp.approve                      (VP — shared with loans)
 */
final class PurchaseRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Admin/super_admin bypass for view-only abilities.
     *
     * C6 FIX: Admins can view procurement data for system administration, but
     * CANNOT bypass SoD-gated approval actions (review, budgetCheck, approve).
     * This prevents a single admin from creating AND approving their own PR.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            $viewAbilities = ['viewAny', 'view', 'create', 'createForDepartment', 'update'];
            if (in_array($ability, $viewAbilities, true)) {
                return true;
            }

            // For approval abilities, fall through to normal policy checks
            // so SoD rules are enforced even for admins
            return null;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.view');
    }

    public function view(User $user, PurchaseRequest $pr): bool
    {
        if (! $user->hasPermissionTo('procurement.purchase-request.view')) {
            return false;
        }

        // Dept heads with create-dept (but not full create) can only see their own dept's PRs.
        if ($this->isDeptHeadScope($user)) {
            return $user->departments()->where('departments.id', $pr->department_id)->exists();
        }

        return true;
    }

    public function create(User $user): bool
    {
        // VP and above can create from any department (bypass all checks)
        if ($user->hasAnyRole(['super_admin', 'executive', 'vice_president'])) {
            return true;
        }

        // Purchasing department users can create PRs for any department
        if ($user->hasPermissionTo('procurement.purchase-request.create')
            && $this->isInPurchasingDepartment($user)) {
            return true;
        }

        // Department heads can create PRs for their own department
        if ($user->hasPermissionTo('procurement.purchase-request.create-dept')
            && $user->hasRole('head')) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can create a PR for a specific department.
     * This is used by the controller to validate department selection.
     */
    public function createForDepartment(User $user, int $departmentId): bool
    {
        // VP and above can create for any department
        if ($user->hasAnyRole(['super_admin', 'executive', 'vice_president'])) {
            return true;
        }

        // Purchasing department users can create for any department
        if ($user->hasPermissionTo('procurement.purchase-request.create')
            && $this->isInPurchasingDepartment($user)) {
            return true;
        }

        // Department heads can only create for their own department(s)
        if ($user->hasPermissionTo('procurement.purchase-request.create-dept')
            && $user->hasRole('head')) {
            // Check primary department
            $primaryDept = $user->relationLoaded('primaryDepartment')
                ? $user->getRelation('primaryDepartment')
                : $user->primaryDepartment;

            if ($primaryDept !== null && $primaryDept->id === $departmentId) {
                return true;
            }

            // Check additional departments (via pivot)
            return $user->departments()->where('departments.id', $departmentId)->exists();
        }

        return false;
    }

    /**
     * Create PR from approved Material Requisition (when stock is insufficient).
     * Only Purchasing department can convert MRQs to PRs.
     */
    public function createFromMrq(User $user, MaterialRequisition $mrq): bool
    {
        // MRQ must be approved and not yet converted
        if (! $mrq->isConvertibleToPr()) {
            return false;
        }

        // Bypass department check for high-level roles
        if ($user->hasAnyRole(['super_admin', 'executive', 'vice_president'])) {
            return true;
        }

        // Only Purchasing department users can convert MRQs.
        return $this->isInPurchasingDepartment($user);
    }

    /**
     * Check if user belongs to Purchasing department.
     */
    /**
     * Returns true when the user is a dept head who can only see/create PRs for their own dept.
     * (Has create-dept but not the full create permission, and is not a high-level role.)
     */
    private function isDeptHeadScope(User $user): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.create-dept')
            && ! $user->hasPermissionTo('procurement.purchase-request.create')
            && ! $user->hasAnyRole(['executive', 'vice_president']);
    }

    private function isInPurchasingDepartment(User $user): bool
    {
        // Check primary department (load if not already loaded)
        /** @var Department|null $primaryDept */
        $primaryDept = $user->relationLoaded('primaryDepartment')
            ? $user->getRelation('primaryDepartment')
            : $user->primaryDepartment;

        if ($primaryDept?->code === 'PURCH') {
            return true;
        }

        // Check additional departments (via pivot)
        return $user->departments()->where('code', 'PURCH')->exists();
    }

    public function update(User $user, PurchaseRequest $pr): bool
    {
        // Must have create permission (either full or dept-level)
        $hasCreatePermission = $user->hasPermissionTo('procurement.purchase-request.create')
            || $user->hasPermissionTo('procurement.purchase-request.create-dept');

        if (! $hasCreatePermission) {
            return false;
        }

        // Can only update draft or returned PRs
        if (! in_array($pr->status, ['draft', 'returned'], true)) {
            return false;
        }

        // Department heads can only update PRs for their own department
        if ($user->hasPermissionTo('procurement.purchase-request.create-dept')
            && ! $user->hasPermissionTo('procurement.purchase-request.create')) {
            return $this->isHeadOfDepartment($user, $pr->department_id);
        }

        return true;
    }

    /**
     * Check if user is head of the specified department.
     */
    private function isHeadOfDepartment(User $user, int $departmentId): bool
    {
        // Check primary department
        $primaryDept = $user->relationLoaded('primaryDepartment')
            ? $user->getRelation('primaryDepartment')
            : $user->primaryDepartment;

        if ($primaryDept !== null && $primaryDept->id === $departmentId) {
            return true;
        }

        // Check additional departments (via pivot)
        return $user->departments()->where('departments.id', $departmentId)->exists();
    }

    public function submit(User $user, PurchaseRequest $pr): bool
    {
        // Must be the requester to submit
        if ($pr->requested_by_id !== $user->id) {
            return false;
        }

        // Can only submit draft or returned PRs
        if (! in_array($pr->status, ['draft', 'returned'], true)) {
            return false;
        }

        // Must have create permission (either full or dept-level)
        $hasCreatePermission = $user->hasPermissionTo('procurement.purchase-request.create')
            || $user->hasPermissionTo('procurement.purchase-request.create-dept');

        if (! $hasCreatePermission) {
            return false;
        }

        // Department heads can only submit PRs for their own department
        if ($user->hasPermissionTo('procurement.purchase-request.create-dept')
            && ! $user->hasPermissionTo('procurement.purchase-request.create')) {
            return $this->isHeadOfDepartment($user, $pr->department_id);
        }

        return true;
    }

    /**
     * Purchasing Department reviews PR for technical validity.
     * Transitions: pending_review → reviewed
     * SoD: Reviewer cannot be the Creator (enforced in service).
     */
    public function review(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.review')
            && $pr->status === 'pending_review';
    }

    /**
     * Accounting verifies budget commitment.
     * SoD: Verifier cannot be the Reviewer (checked in service).
     */
    public function budgetCheck(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('procurement.purchase-request.budget-check')
            && $pr->status === 'reviewed';
    }

    /**
     * Return PR for revision — Purchasing at pending_review, Accounting at reviewed.
     */
    public function returnForRevision(User $user, PurchaseRequest $pr): bool
    {
        // Purchasing can return at pending_review stage
        if ($pr->status === 'pending_review') {
            return $user->hasPermissionTo('procurement.purchase-request.review');
        }

        // Accounting can return at reviewed stage
        if ($pr->status === 'reviewed') {
            return $user->hasPermissionTo('procurement.purchase-request.budget-check');
        }

        return false;
    }

    /**
     * VP gives final approval.
     * SoD: VP cannot be the Budget Verifier (checked in service).
     */
    public function vpApprove(User $user, PurchaseRequest $pr): bool
    {
        return $user->hasPermissionTo('approvals.vp.approve')
            && $pr->status === 'budget_verified';
    }

    /**
     * Reject — allowed by whoever has authority at current stage.
     */
    public function reject(User $user, PurchaseRequest $pr): bool
    {
        return match ($pr->status) {
            'pending_review' => $user->hasPermissionTo('procurement.purchase-request.review'),
            'reviewed' => $user->hasPermissionTo('procurement.purchase-request.budget-check'),
            'budget_verified' => $user->hasPermissionTo('approvals.vp.approve'),
            default => false,
        };
    }

    public function cancel(User $user, PurchaseRequest $pr): bool
    {
        // Must be the requester to cancel
        if ($pr->requested_by_id !== $user->id) {
            return false;
        }

        // Can only cancel draft PRs
        if ($pr->status !== 'draft') {
            return false;
        }

        // Must have create permission (either full or dept-level)
        $hasCreatePermission = $user->hasPermissionTo('procurement.purchase-request.create')
            || $user->hasPermissionTo('procurement.purchase-request.create-dept');

        if (! $hasCreatePermission) {
            return false;
        }

        // Department heads can only cancel PRs for their own department
        if ($user->hasPermissionTo('procurement.purchase-request.create-dept')
            && ! $user->hasPermissionTo('procurement.purchase-request.create')) {
            return $this->isHeadOfDepartment($user, $pr->department_id);
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(\App\Models\User $user, $model): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(\App\Models\User $user, $model): bool
    {
        return $user->hasRole('super_admin');
    }
}
