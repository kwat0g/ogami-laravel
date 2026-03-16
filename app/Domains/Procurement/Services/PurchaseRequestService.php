<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\HR\Models\Department;
use App\Domains\Procurement\Models\PurchaseRequest;
use App\Domains\Procurement\Models\PurchaseRequestItem;
use App\Models\User;
use App\Notifications\Procurement\PurchaseRequestStatusNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\SodViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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

        return DB::transaction(function () use ($data, $items, $actor): PurchaseRequest {
            $reference = $this->generateReference();

            $pr = PurchaseRequest::create([
                'pr_reference' => $reference,
                'department_id' => $data['department_id'],
                'requested_by_id' => $actor->id,                'vendor_id' => $data['vendor_id'],                'urgency' => $data['urgency'] ?? 'normal',
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

        // Hierarchy Bypass: High-ranking users skip the "Staff -> Head" (Submitted -> Noted) wait.
        // If the submitter is already a Head, Manager, Officer, or VP, we auto-transition to 'noted'.
        $canBypassNote = $actor->hasAnyPermission([
            'procurement.purchase-request.note',   // Head role
            'procurement.purchase-request.check',  // Manager role
            'procurement.purchase-request.review', // Officer role
            'approvals.vp.approve',                // VP/Exec role
        ]);

        $targetStatus = $canBypassNote ? 'noted' : 'submitted';

        $updateData = [
            'status' => $targetStatus,
            'submitted_by_id' => $actor->id,
            'submitted_at' => now(),
        ];

        if ($targetStatus === 'noted') {
            $updateData['noted_by_id'] = $actor->id;
            $updateData['noted_at'] = now();
            $updateData['noted_comments'] = 'Auto-noted (Hierarchy Bypass)';
        }

        $pr->update($updateData);

        $refreshed = $pr->refresh();

        if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
            Notification::send($refreshed->requestedBy, new PurchaseRequestStatusNotification($refreshed, $targetStatus, $actor->name));
        }

        return $refreshed;
    }

    // ── Note (Head — SOD-011) ────────────────────────────────────────────────

    public function note(PurchaseRequest $pr, User $actor, string $comments = ''): PurchaseRequest
    {
        $this->assertStatus($pr, 'submitted', 'PR_NOT_SUBMITTED');
        $this->assertSod($actor, $pr->submitted_by_id ?? 0, 'SOD-011', 'Head cannot be the same person who submitted the PR.');

        $pr->update([
            'status' => 'noted',
            'noted_by_id' => $actor->id,
            'noted_at' => now(),
            'noted_comments' => $comments,
        ]);

        $refreshed = $pr->refresh();

        if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
            Notification::send($refreshed->requestedBy, new PurchaseRequestStatusNotification($refreshed, 'noted', $actor->name, $comments ?: null));
        }

        return $refreshed;
    }

    // ── Check (Manager — SOD-012) ────────────────────────────────────────────

    public function check(PurchaseRequest $pr, User $actor, string $comments = ''): PurchaseRequest
    {
        $this->assertStatus($pr, 'noted', 'PR_NOT_NOTED');

        if ($pr->requested_by_id === $actor->id && ! $actor->hasRole('super_admin')) {
            throw new SodViolationException(
                'purchase_request',
                'check',
                'Manager checker must differ from requester (SoD).',
            );
        }

        $this->assertSod($actor, $pr->noted_by_id ?? 0, 'SOD-012', 'Manager cannot be the same person who noted the PR.');

        $pr->update([
            'status' => 'checked',
            'checked_by_id' => $actor->id,
            'checked_at' => now(),
            'checked_comments' => $comments,
        ]);

        $refreshed = $pr->refresh();

        if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
            Notification::send($refreshed->requestedBy, new PurchaseRequestStatusNotification($refreshed, 'checked', $actor->name, $comments ?: null));
        }

        return $refreshed;
    }

    // ── Review (Officer — SOD-013) ───────────────────────────────────────────

    public function review(PurchaseRequest $pr, User $actor, string $comments = ''): PurchaseRequest
    {
        $this->assertStatus($pr, 'checked', 'PR_NOT_CHECKED');

        if ($pr->requested_by_id === $actor->id && ! $actor->hasRole('super_admin')) {
            throw new SodViolationException(
                'purchase_request',
                'review',
                'Officer reviewer must differ from requester (SoD).',
            );
        }

        $this->assertSod($actor, $pr->checked_by_id ?? 0, 'SOD-013', 'Officer cannot be the same person who checked the PR.');

        $pr->update([
            'status' => 'reviewed',
            'reviewed_by_id' => $actor->id,
            'reviewed_at' => now(),
            'reviewed_comments' => $comments,
        ]);

        $refreshed = $pr->refresh();

        if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
            Notification::send($refreshed->requestedBy, new PurchaseRequestStatusNotification($refreshed, 'reviewed', $actor->name, $comments ?: null));
        }

        return $refreshed;
    }
    // ── Budget-check (Accounting Officer — after 'reviewed') ───────────────────────────

    public function budgetCheck(PurchaseRequest $pr, User $actor, string $comments = ''): PurchaseRequest
    {
        $this->assertStatus($pr, 'reviewed', 'PR_NOT_REVIEWED');

        if ($pr->requested_by_id === $actor->id && ! $actor->hasRole('super_admin')) {
            throw new SodViolationException(
                'purchase_request',
                'budget_check',
                'Budget checker must differ from requester (SoD).',
            );
        }

        // ── Real budget enforcement ───────────────────────────────────────────
        /** @var Department|null $dept */
        $dept = Department::find($pr->department_id);
        if ($dept !== null && $dept->annual_budget_centavos > 0) {
            $startMonth = (int) $dept->fiscal_year_start_month;
            $now = now();
            $fyStart = $now->copy()->month($startMonth)->startOfMonth();
            if ($fyStart->gt($now)) {
                $fyStart->subYear();
            }

            $ytdSpend = (int) PurchaseRequest::where('department_id', $pr->department_id)
                ->where('id', '!=', $pr->id)
                ->whereIn('status', ['budget_checked', 'vp_approved'])
                ->where('created_at', '>=', $fyStart)
                ->sum('total_estimated_cost');

            $prAmount = (int) $pr->total_estimated_cost;

            if (($ytdSpend + $prAmount) > $dept->annual_budget_centavos) {
                $fmt = fn (int $c) => '₱'.number_format($c / 100, 2);
                throw new DomainException(
                    message: sprintf(
                        'Budget exceeded. Department budget: %s. YTD spend: %s. This PR: %s.',
                        $fmt($dept->annual_budget_centavos),
                        $fmt($ytdSpend),
                        $fmt($prAmount),
                    ),
                    errorCode: 'PR_BUDGET_EXCEEDED',
                    httpStatus: 422,
                );
            }
        }

        $pr->update([
            'status' => 'budget_checked',
            'budget_checked_by_id' => $actor->id,
            'budget_checked_at' => now(),
            'budget_checked_comments' => $comments,
        ]);

        $refreshed = $pr->refresh();

        // Notify VP — PR is ready for final approval
        User::permission('approvals.vp.approve')
            ->where('id', '!=', $actor->id)
            ->each(fn (User $u) => $u->notify(
                new PurchaseRequestStatusNotification($refreshed, 'budget_checked', $actor->name, $comments ?: null)
            ));

        return $refreshed;
    }

    // ── Return for revision (Accounting Officer sends back to requester) ────────────

    public function returnForRevision(PurchaseRequest $pr, User $actor, string $reason): PurchaseRequest
    {
        $this->assertStatus($pr, 'reviewed', 'PR_NOT_REVIEWED');

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

        // Notify the requester so they know to revise and resubmit
        if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
            Notification::send($refreshed->requestedBy, new PurchaseRequestStatusNotification($refreshed, 'returned', $actor->name, $reason));
        }

        return $refreshed;
    }
    // ── VP Approve (SOD-014) ─────────────────────────────────────────────────

    public function vpApprove(PurchaseRequest $pr, User $actor, string $comments = ''): PurchaseRequest
    {
        $this->assertStatus($pr, 'budget_checked', 'PR_NOT_BUDGET_CHECKED');

        if ($pr->requested_by_id === $actor->id && ! $actor->hasRole('super_admin')) {
            throw new SodViolationException(
                'purchase_request',
                'vp_approve',
                'VP approver must differ from requester (SoD).',
            );
        }

        $this->assertSod($actor, $pr->budget_checked_by_id ?? 0, 'SOD-014', 'VP cannot be the same person who budget-checked the PR.');

        return DB::transaction(function () use ($pr, $actor, $comments) {
            $pr->update([
                'status' => 'approved',
                'vp_approved_by_id' => $actor->id,
                'vp_approved_at' => now(),
                'vp_comments' => $comments,
            ]);

            $refreshed = $pr->refresh();

            // Auto-create Purchase Order draft
            // If this fails, the entire transaction (including PR approval) rolls back.
            $this->purchaseOrderService->createFromApprovedPr($refreshed);

            if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
                Notification::send($refreshed->requestedBy, new PurchaseRequestStatusNotification($refreshed, 'approved', $actor->name, $comments ?: null));
            }

            // Notify Purchasing Officers so they can assign vendor and finalize the auto-created PO.
            User::permission('procurement.purchase-order.create')
                ->where('id', '!=', $actor->id)
                ->each(fn (User $u) => $u->notify(
                    new PurchaseRequestStatusNotification($refreshed, 'approved', $actor->name, $comments ?: null)
                ));

            return $refreshed;
        });
    }

    // ── Reject ───────────────────────────────────────────────────────────────

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
            Notification::send($refreshed->requestedBy, new PurchaseRequestStatusNotification($refreshed, 'rejected', $actor->name, $reason));
        }

        return $refreshed;
    }

    // ── Cancel ───────────────────────────────────────────────────────────────

    public function cancel(PurchaseRequest $pr, User $actor): PurchaseRequest
    {
        if (! in_array($pr->status, ['draft', 'submitted'], true)) {
            throw new DomainException(
                message: 'Only draft or submitted Purchase Requests can be cancelled.',
                errorCode: 'PR_CANNOT_CANCEL',
                httpStatus: 422,
            );
        }

        $pr->update(['status' => 'cancelled']);

        $refreshed = $pr->refresh();

        if ($refreshed->requestedBy !== null && $refreshed->requestedBy->id !== $actor->id) {
            Notification::send($refreshed->requestedBy, new PurchaseRequestStatusNotification($refreshed, 'cancelled', $actor->name));
        }

        return $refreshed;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(PurchaseRequest $pr, array $items): void
    {
        $pr->items()->delete();

        foreach ($items as $index => $item) {
            PurchaseRequestItem::create([
                'purchase_request_id' => $pr->id,
                'vendor_item_id' => $item['vendor_item_id'] ?? null,
                'item_description' => $item['item_description'],
                'unit_of_measure' => $item['unit_of_measure'],
                'quantity' => $item['quantity'],
                'estimated_unit_cost' => $item['estimated_unit_cost'],
                'specifications' => $item['specifications'] ?? null,
                'line_order' => $index + 1,
            ]);
        }
    }

    private function generateReference(): string
    {
        $seq = DB::selectOne('SELECT NEXTVAL(\'purchase_request_seq\') AS val');
        $num = str_pad((string) $seq->val, 5, '0', STR_PAD_LEFT);

        return 'PR-'.now()->format('Y-m').'-'.$num;
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
        // super_admin bypasses all SoD constraints (testing superuser)
        if ($actor->hasRole('super_admin')) {
            return;
        }

        if ($actor->id === $previousActorId) {
            throw new DomainException(
                message: "{$sodCode}: {$message}",
                errorCode: $sodCode,
                httpStatus: 422,
            );
        }
    }
}
