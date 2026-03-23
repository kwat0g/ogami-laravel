<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\HR\Models\Department;
use App\Domains\Inventory\Models\MaterialRequisition;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Procurement\Models\PurchaseRequestItem;
use App\Models\User;
use App\Notifications\Procurement\PurchaseRequestStatusNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\SodViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Purchase Request Service - Simplified 4-Stage Workflow
 * 
 * New Workflow:
 *   draft → pending_review → reviewed → budget_verified → approved → converted_to_po
 * 
 * Stages:
 *   1. pending_review: Purchasing Dept reviews technical validity
 *   2. reviewed:       Purchasing confirms PR is valid
 *   3. budget_verified: Accounting commits budget
 *   4. approved:       VP gives final authority → Auto-creates PO
 * 
 * SoD Constraints:
 *   - Reviewer ≠ Creator
 *   - Budget Verifier ≠ Reviewer  
 *   - VP ≠ Budget Verifier
 */
final class PurchaseRequestService implements ServiceContract
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {}

    // ── Store (draft) ────────────────────────────────────────────────────────

    /**
     * Create a new PR draft with line items.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function store(array $data, array $items, User $actor): PurchaseRequest
    {
        if (empty($items)) {
            throw new DomainException(
                message: 'A Purchase Request must have at least one line item before it can be saved.',
                errorCode: 'PR_NO_ITEMS',
                httpStatus: 422,
            );
        }

        // ── Budget Pre-Validation ─────────────────────────────────────────────
        $departmentId = $data['department_id'];
        $dept = Department::find($departmentId);

        if ($dept !== null && $dept->annual_budget_centavos > 0) {
            $this->validateBudgetAvailability($dept, $items, null);
        }

        return DB::transaction(function () use ($data, $items, $actor): PurchaseRequest {
            $reference = $this->generateReference();

            $pr = PurchaseRequest::create([
                'pr_reference' => $reference,
                'department_id' => $data['department_id'],
                'requested_by_id' => $actor->id,
                'vendor_id' => $data['vendor_id'] ?? null,
                'urgency' => $data['urgency'] ?? 'normal',
                'justification' => $data['justification'],
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'total_estimated_cost' => 0,
            ]);

            $this->syncItems($pr, $items);

            return $pr->refresh();
        });
    }

    // ── Update ───────────────────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function update(PurchaseRequest $pr, array $data, array $items): PurchaseRequest
    {
        if (! in_array($pr->status, ['draft', 'returned'], true)) {
            throw new DomainException(
                message: 'Only draft or returned Purchase Requests can be edited.',
                errorCode: 'PR_NOT_EDITABLE',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($pr, $data, $items): PurchaseRequest {
            $pr->update([
                'department_id' => $data['department_id'] ?? $pr->department_id,
                'vendor_id' => $data['vendor_id'] ?? $pr->vendor_id,
                'urgency' => $data['urgency'] ?? $pr->urgency,
                'justification' => $data['justification'] ?? $pr->justification,
                'notes' => $data['notes'] ?? $pr->notes,
            ]);

            if (! empty($items)) {
                $this->syncItems($pr, $items);
            }

            return $pr->refresh();
        });
    }

    // ── Submit ───────────────────────────────────────────────────────────────
    /**
     * Submit PR for review.
     *
     * Auto-advance rules (skip stages based on submitter authority):
     *   - VP submits            → budget_verified  (skips review + no budget check needed from another)
     *   - Purchasing Manager    → reviewed          (self-reviews; Accounting does budget check next)
     *   - Everyone else         → pending_review    (full workflow)
     */
    public function submit(PurchaseRequest $pr, User $actor): PurchaseRequest
    {
        if (! in_array($pr->status, ['draft', 'returned'], true)) {
            throw new DomainException(
                message: "Purchase Request must be in 'draft' or 'returned' status to submit (current: '{$pr->status}').",
                errorCode: 'PR_NOT_DRAFT',
                httpStatus: 422,
            );
        }

        if ($pr->items()->count() === 0) {
            throw new DomainException(
                message: 'Cannot submit — the Purchase Request has no line items.',
                errorCode: 'PR_NO_ITEMS',
                httpStatus: 422,
            );
        }

        // VP auto-advances past both review stages
        if ($actor->can('approvals.vp.approve')) {
            $targetStatus = 'budget_verified';
            $updateData = [
                'status'           => $targetStatus,
                'submitted_by_id'  => $actor->id,
                'submitted_at'     => now(),
                'reviewed_by_id'   => $actor->id,
                'reviewed_at'      => now(),
                'reviewed_comments' => 'Auto-reviewed (VP Authority)',
            ];
        } elseif ($this->isPurchasingManager($actor)) {
            // Purchasing Manager self-reviews — jumps directly to Budget Verification
            $targetStatus = 'reviewed';
            $updateData = [
                'status'            => $targetStatus,
                'submitted_by_id'   => $actor->id,
                'submitted_at'      => now(),
                'reviewed_by_id'    => $actor->id,
                'reviewed_at'       => now(),
                'reviewed_comments' => 'Auto-reviewed (Purchasing Manager)',
            ];
        } else {
            $targetStatus = 'pending_review';
            $updateData = [
                'status'           => $targetStatus,
                'submitted_by_id'  => $actor->id,
                'submitted_at'     => now(),
            ];
        }

        $pr->update($updateData);
        $refreshed = $pr->refresh();

        // Notify requester (if different from actor)
        if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
            Notification::send($refreshed->requestedBy, PurchaseRequestStatusNotification::fromModel($refreshed, $targetStatus, $actor->name));
        }

        if ($targetStatus === 'pending_review') {
            // Notify Purchasing Dept (other officers/managers can review)
            User::permission('procurement.purchase-request.review')
                ->where('id', '!=', $actor->id)
                ->each(fn (User $u) => $u->notify(
                    PurchaseRequestStatusNotification::fromModel($refreshed, 'pending_review', $actor->name, 'PR awaiting Purchasing review')
                ));
        } elseif ($targetStatus === 'reviewed') {
            // Purchasing Manager submitted — notify Accounting to do budget check
            User::permission('procurement.purchase-request.budget-check')
                ->where('id', '!=', $actor->id)
                ->each(fn (User $u) => $u->notify(
                    PurchaseRequestStatusNotification::fromModel($refreshed, 'reviewed', $actor->name, 'PR ready for budget verification (submitted by Purchasing Manager)')
                ));
        }

        return $refreshed;
    }

    /**
     * True when the actor is a Manager assigned to the Purchasing department.
     */
    private function isPurchasingManager(User $actor): bool
    {
        if (! $actor->hasRole('manager')) {
            return false;
        }

        $primaryDept = $actor->relationLoaded('primaryDepartment')
            ? $actor->getRelation('primaryDepartment')
            : $actor->primaryDepartment;

        if ($primaryDept?->code === 'PURCH') {
            return true;
        }

        return $actor->departments()->where('code', 'PURCH')->exists();
    }

    // ── Review (Purchasing Dept) ─────────────────────────────────────────────
    /**
     * Purchasing Department reviews PR for technical validity.
     * Transitions: pending_review → reviewed
     * 
     * SoD: Reviewer cannot be the Creator
     */

    public function review(PurchaseRequest $pr, User $actor, string $comments = ''): PurchaseRequest
    {
        $this->assertStatus($pr, 'pending_review', 'PR_NOT_PENDING_REVIEW');

        // SoD: Creator cannot review their own PR
        if ($pr->requested_by_id === $actor->id && ! $actor->hasRole('super_admin')) {
            throw new SodViolationException(
                'purchase_request',
                'review',
                'Purchasing reviewer cannot be the same person who created the PR (SoD).',
            );
        }

        $pr->update([
            'status' => 'reviewed',
            'reviewed_by_id' => $actor->id,
            'reviewed_at' => now(),
            'reviewed_comments' => $comments,
        ]);

        $refreshed = $pr->refresh();

        // Notify requester
        if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
            Notification::send($refreshed->requestedBy, PurchaseRequestStatusNotification::fromModel($refreshed, 'reviewed', $actor->name, $comments ?: null));
        }

        // Notify Accounting that PR is ready for budget verification
        User::permission('procurement.purchase-request.budget-check')
            ->where('id', '!=', $actor->id)
            ->each(fn (User $u) => $u->notify(
                PurchaseRequestStatusNotification::fromModel($refreshed, 'reviewed', $actor->name, 'PR ready for budget verification')
            ));

        return $refreshed;
    }

    // ── Budget Verification (Accounting Officer) ─────────────────────────────
    /**
     * Accounting verifies that funds are committed for this PR.
     * Transitions: reviewed → budget_verified
     * 
     * SoD: Budget verifier cannot be the Creator or the Reviewer
     */

    public function budgetCheck(PurchaseRequest $pr, User $actor, string $comments = ''): PurchaseRequest
    {
        $this->assertStatus($pr, 'reviewed', 'PR_NOT_REVIEWED');

        // SoD: Budget verifier cannot be creator
        if ($pr->requested_by_id === $actor->id && ! $actor->hasRole('super_admin')) {
            throw new SodViolationException(
                'purchase_request',
                'budget_check',
                'Budget verifier cannot be the same person who created the PR (SoD).',
            );
        }

        // SoD: Budget verifier cannot be the reviewer
        $this->assertSod($actor, $pr->reviewed_by_id ?? 0, 'SOD-BC-01', 'Budget verifier cannot be the same person who reviewed the PR.');

        $pr->update([
            'status' => 'budget_verified',
            'budget_checked_by_id' => $actor->id,
            'budget_checked_at' => now(),
            'budget_checked_comments' => $comments,
        ]);

        $refreshed = $pr->refresh();

        // Notify requester
        if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
            Notification::send($refreshed->requestedBy, PurchaseRequestStatusNotification::fromModel($refreshed, 'budget_verified', $actor->name, $comments ?: null));
        }

        // Notify VP — PR is ready for final approval
        User::permission('approvals.vp.approve')
            ->where('id', '!=', $actor->id)
            ->each(fn (User $u) => $u->notify(
                PurchaseRequestStatusNotification::fromModel($refreshed, 'budget_verified', $actor->name, 'PR ready for VP approval')
            ));

        return $refreshed;
    }

    // ── Return for revision ──────────────────────────────────────────────────
    /**
     * Purchasing or Accounting can return PR for revision.
     * Can return from pending_review or reviewed stages.
     */

    public function returnForRevision(PurchaseRequest $pr, User $actor, string $reason): PurchaseRequest
    {
        if (! in_array($pr->status, ['pending_review', 'reviewed'], true)) {
            throw new DomainException(
                message: "Cannot return PR in status '{$pr->status}'. Must be pending_review or reviewed.",
                errorCode: 'PR_CANNOT_RETURN',
                httpStatus: 422,
            );
        }

        if (trim($reason) === '') {
            throw new DomainException(
                message: 'A return reason is required.',
                errorCode: 'PR_RETURN_REASON_REQUIRED',
                httpStatus: 422,
            );
        }

        $pr->update([
            'status' => 'returned',
            'returned_by_id' => $actor->id,
            'returned_at' => now(),
            'return_reason' => $reason,
        ]);

        $refreshed = $pr->refresh();

        // Notify the requester
        if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
            Notification::send($refreshed->requestedBy, PurchaseRequestStatusNotification::fromModel($refreshed, 'returned', $actor->name, $reason));
        }

        return $refreshed;
    }

    // ── VP Approve (Final Authority) ─────────────────────────────────────────
    /**
     * VP gives final approval. PR must be budget_verified first.
     * SoD: VP cannot be the Budget Verifier
     */

    public function vpApprove(PurchaseRequest $pr, User $actor, string $comments = ''): PurchaseRequest
    {
        $this->assertStatus($pr, 'budget_verified', 'PR_NOT_BUDGET_VERIFIED');

        // SoD: VP cannot be creator
        if ($pr->requested_by_id === $actor->id && ! $actor->hasRole('super_admin')) {
            throw new SodViolationException(
                'purchase_request',
                'vp_approve',
                'VP approver must differ from requester (SoD).',
            );
        }

        // SoD: VP cannot be the budget verifier
        $this->assertSod($actor, $pr->budget_checked_by_id ?? 0, 'SOD-VP-01', 'VP cannot be the same person who verified the budget.');

        return DB::transaction(function () use ($pr, $actor, $comments) {
            $pr->update([
                'status' => 'approved',
                'vp_approved_by_id' => $actor->id,
                'vp_approved_at' => now(),
                'vp_comments' => $comments,
            ]);

            $refreshed = $pr->refresh();

            // Auto-create Purchase Order draft
            $this->purchaseOrderService->createFromApprovedPr($refreshed);

            // Notify requester
            if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
                Notification::send($refreshed->requestedBy, PurchaseRequestStatusNotification::fromModel($refreshed, 'approved', $actor->name, $comments ?: null));
            }

            // Notify Purchasing Officers to finalize PO
            User::permission('procurement.purchase-order.create')
                ->where('id', '!=', $actor->id)
                ->each(fn (User $u) => $u->notify(
                    PurchaseRequestStatusNotification::fromModel($refreshed, 'approved', $actor->name, 'PR approved - PO created')
                ));

            return $refreshed;
        });
    }

    // ── Reject ───────────────────────────────────────────────────────────────
    /**
     * Reject PR at any stage before approval.
     */

    public function reject(PurchaseRequest $pr, User $actor, string $reason, string $stage): PurchaseRequest
    {
        if (in_array($pr->status, ['approved', 'rejected', 'cancelled', 'converted_to_po'], true)) {
            throw new DomainException(
                message: "Cannot reject a PR in status '{$pr->status}'.",
                errorCode: 'PR_CANNOT_REJECT',
                httpStatus: 422,
            );
        }

        $pr->update([
            'status' => 'rejected',
            'rejected_by_id' => $actor->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'rejection_stage' => $stage,
        ]);

        $refreshed = $pr->refresh();

        if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
            Notification::send($refreshed->requestedBy, PurchaseRequestStatusNotification::fromModel($refreshed, 'rejected', $actor->name, $reason));
        }

        return $refreshed;
    }

    // ── Cancel ───────────────────────────────────────────────────────────────

    public function cancel(PurchaseRequest $pr, User $actor): PurchaseRequest
    {
        if ($pr->status !== 'draft') {
            throw new DomainException(
                message: "Cannot cancel PR in status '{$pr->status}'. Only draft PRs can be cancelled.",
                errorCode: 'PR_CANNOT_CANCEL',
                httpStatus: 422,
            );
        }

        $pr->update([
            'status' => 'cancelled',
            'cancelled_by_id' => $actor->id,
            'cancelled_at' => now(),
        ]);

        return $pr->refresh();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function syncItems(PurchaseRequest $pr, array $items): void
    {
        // Delete existing items
        $pr->items()->delete();

        // Create new items
        foreach ($items as $item) {
            PurchaseRequestItem::create([
                'purchase_request_id' => $pr->id,
                'item_description' => $item['item_description'],
                'quantity' => $item['quantity'],
                'unit_of_measure' => $item['unit_of_measure'],
                'estimated_unit_cost' => $item['estimated_unit_cost'],
                'vendor_item_id' => $item['vendor_item_id'] ?? null,
            ]);
        }

        // Recalculate total
        $total = $pr->items()->sum(DB::raw('quantity * estimated_unit_cost'));
        $pr->update(['total_estimated_cost' => $total]);
    }

    private function generateReference(): string
    {
        $prefix = 'PR-' . date('Y') . '-';
        $last = PurchaseRequest::whereYear('created_at', date('Y'))
            ->orderByDesc('id')
            ->first();
        
        $number = $last ? (int) substr($last->pr_reference, -5) + 1 : 1;
        
        return $prefix . str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    private function assertStatus(PurchaseRequest $pr, string $expected, string $errorCode): void
    {
        if ($pr->status !== $expected) {
            throw new DomainException(
                message: "Purchase Request must be in '{$expected}' status (current: '{$pr->status}').",
                errorCode: $errorCode,
                httpStatus: 422,
            );
        }
    }

    private function assertSod(User $actor, int $previousActorId, string $sodCode, string $message): void
    {
        // super_admin bypasses all SoD constraints
        if ($actor->hasRole('super_admin')) {
            return;
        }

        if ($actor->id === $previousActorId) {
            throw new DomainException(
                message: "{$sodCode}: {$message}",
                errorCode: $sodCode,
                httpStatus: 403,
            );
        }
    }

    /**
     * Validate that department has sufficient budget for this PR.
     * Uses the department's fiscal year start month (not calendar year).
     */
    private function validateBudgetAvailability(Department $dept, array $items, ?int $excludePrId): void
    {
        $startMonth = (int) ($dept->fiscal_year_start_month ?? 1);
        $now = now();
        $fyStart = $now->copy()->month($startMonth)->startOfMonth();
        if ($fyStart->gt($now)) {
            $fyStart->subYear();
        }

        // Calculate YTD spend from approved/budget-verified PRs
        $ytdSpend = (int) PurchaseRequest::where('department_id', $dept->id)
            ->when($excludePrId !== null, fn ($q) => $q->where('id', '!=', $excludePrId))
            ->whereIn('status', ['budget_verified', 'approved', 'converted_to_po'])
            ->where('created_at', '>=', $fyStart)
            ->sum('total_estimated_cost');

        // Calculate this PR amount (convert to centavos)
        $prAmount = (int) collect($items)->sum(
            fn ($item) => ($item['quantity'] ?? 0) * ($item['estimated_unit_cost'] ?? 0) * 100
        );

        if (($ytdSpend + $prAmount) > $dept->annual_budget_centavos) {
            $fmt = fn (int $c): string => '₱' . number_format($c / 100, 2);
            throw new DomainException(
                message: "Insufficient budget: Dept {$dept->name} has {$fmt($dept->annual_budget_centavos - $ytdSpend)} remaining, but this PR requires {$fmt($prAmount)}.",
                errorCode: 'PR_BUDGET_EXCEEDED',
                httpStatus: 422,
            );
        }
    }

    // ── Create PR from approved Material Requisition ─────────────────────────

    /**
     * Create a Purchase Request from an approved Material Requisition.
     */
    public function createFromMrq(MaterialRequisition $mrq, User $actor, ?string $justification = null): PurchaseRequest
    {
        if ($mrq->status !== 'approved') {
            throw new DomainException(
                message: "Material Requisition must be approved to convert to PR (current: '{$mrq->status}').",
                errorCode: 'MRQ_NOT_APPROVED',
                httpStatus: 422,
            );
        }

        if ($mrq->converted_to_pr) {
            throw new DomainException(
                message: 'Material Requisition has already been converted to a Purchase Request.',
                errorCode: 'MRQ_ALREADY_CONVERTED',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($mrq, $actor, $justification): PurchaseRequest {
            $reference = $this->generateReference();

            $pr = PurchaseRequest::create([
                'pr_reference' => $reference,
                'department_id' => $mrq->department_id,
                'requested_by_id' => $actor->id,
                'vendor_id' => null,
                'urgency' => 'normal',
                'justification' => $justification ?? "Created from Material Requisition {$mrq->mrq_reference}",
                'notes' => "Source MRQ: {$mrq->mrq_reference}",
                'status' => 'draft',
                'total_estimated_cost' => 0,
                'material_requisition_id' => $mrq->id,
            ]);

            // Convert MRQ items to PR items
            foreach ($mrq->items as $mrqItem) {
                PurchaseRequestItem::create([
                    'purchase_request_id' => $pr->id,
                    'item_description' => $mrqItem->item_description,
                    'quantity' => $mrqItem->quantity_requested - $mrqItem->quantity_issued,
                    'unit_of_measure' => $mrqItem->unit_of_measure,
                    'estimated_unit_cost' => 0, // Will be filled during vendor selection
                ]);
            }

            // Recalculate total
            $total = $pr->items()->sum(DB::raw('quantity * estimated_unit_cost'));
            $pr->update(['total_estimated_cost' => $total]);

            // Mark MRQ as converted
            $mrq->update(['converted_to_pr' => true]);

            return $pr->refresh();
        });
    }

    // ── Auto-create PR from Low Stock (Reorder Point Monitor) ────────────────

    /**
     * Called by CheckReorderPointsCommand when a stock item falls below its reorder point.
     * Creates a draft PR assigned to the Purchasing department (or first active dept as fallback).
     * Skips duplicate creation — the command's prExistsForItem() check handles that.
     */
    public function autoCreateFromLowStock(
        int $itemId,
        string $itemCode,
        string $itemName,
        string $unitOfMeasure,
        float $reorderPoint,
        float $currentStock,
        float $reorderQty,
        User $actor,
    ): PurchaseRequest {
        return DB::transaction(function () use ($itemId, $itemCode, $itemName, $unitOfMeasure, $reorderPoint, $currentStock, $reorderQty, $actor): PurchaseRequest {
            // Use Purchasing department; fallback to first active department
            $dept = Department::where('code', 'PURCH')->where('is_active', true)->first()
                ?? Department::where('is_active', true)->first();

            if ($dept === null) {
                throw new DomainException(
                    message: 'No active department found to assign auto-created PR.',
                    errorCode: 'PR_NO_DEPARTMENT',
                    httpStatus: 500,
                );
            }

            $pr = PurchaseRequest::create([
                'pr_reference' => $this->generateReference(),
                'department_id' => $dept->id,
                'requested_by_id' => $actor->id,
                'vendor_id' => null,
                'urgency' => 'normal',
                'justification' => "[{$itemCode}] stock ({$currentStock}) is below reorder point ({$reorderPoint}). Auto-created by reorder monitor.",
                'notes' => 'Auto-generated by inventory reorder point monitor.',
                'status' => 'draft',
                'total_estimated_cost' => 0,
            ]);

            PurchaseRequestItem::create([
                'purchase_request_id' => $pr->id,
                'item_description' => "[{$itemCode}] {$itemName}",
                'quantity' => $reorderQty,
                'unit_of_measure' => $unitOfMeasure,
                'estimated_unit_cost' => 0,
            ]);

            return $pr->refresh();
        });
    }

    // ── Duplicate Purchase Request ─────────────────────────────────────────────

    /**
     * Duplicate an existing Purchase Request with a new reference.
     * Copies all fields and line items, sets status to 'draft'.
     */
    public function duplicate(int $prId, User $actor): PurchaseRequest
    {
        $originalPr = PurchaseRequest::with('items')->find($prId);

        if ($originalPr === null) {
            throw new DomainException(
                message: 'Purchase Request not found.',
                errorCode: 'PR_NOT_FOUND',
                httpStatus: 404,
            );
        }

        // Only allow duplication of PRs that have been converted to a PO
        if ($originalPr->status !== 'converted_to_po') {
            throw new DomainException(
                message: "Cannot duplicate a PR with status '{$originalPr->status}'. Only PRs that have been converted to a PO can be duplicated.",
                errorCode: 'PR_CANNOT_DUPLICATE',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($originalPr, $actor): PurchaseRequest {
            $reference = $this->generateReference();

            // Create new PR with copied fields
            $newPr = PurchaseRequest::create([
                'pr_reference' => $reference,
                'department_id' => $originalPr->department_id,
                'requested_by_id' => $actor->id,
                'vendor_id' => $originalPr->vendor_id,
                'urgency' => $originalPr->urgency,
                'justification' => $originalPr->justification,
                'notes' => $originalPr->notes !== null
                    ? "Duplicated from {$originalPr->pr_reference}.\n\n{$originalPr->notes}"
                    : "Duplicated from {$originalPr->pr_reference}.",
                'status' => 'draft',
                'total_estimated_cost' => 0,
            ]);

            // Copy all line items
            foreach ($originalPr->items as $item) {
                PurchaseRequestItem::create([
                    'purchase_request_id' => $newPr->id,
                    'item_description' => $item->item_description,
                    'quantity' => $item->quantity,
                    'unit_of_measure' => $item->unit_of_measure,
                    'estimated_unit_cost' => $item->estimated_unit_cost,
                    'vendor_item_id' => $item->vendor_item_id,
                    'specifications' => $item->specifications,
                    'line_order' => $item->line_order,
                ]);
            }

            // Recalculate total
            $total = $newPr->items()->sum(DB::raw('quantity * estimated_unit_cost'));
            $newPr->update(['total_estimated_cost' => $total]);

            return $newPr->refresh();
        });
    }
}
