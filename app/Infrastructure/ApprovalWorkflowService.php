<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\SodViolationException;
use App\Shared\Models\ApprovalLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * F-007: Configurable approval workflow engine.
 *
 * Provides a reusable approval service for all document types:
 * Leave, Loan, Purchase Request, Purchase Order, Budget, Payroll.
 *
 * Usage:
 *   $nextStep = $this->workflowService->getNextStep('leave_request', $request->status, $request->amount);
 *   $this->workflowService->approve($request, $user, 'Approved with conditions');
 *
 * The service reads approval_workflow_configs to determine:
 * 1. What steps exist for this document type
 * 2. What permission is required at each step
 * 3. Whether SoD constraints are met
 * 4. What status the document should move to
 */
final class ApprovalWorkflowService implements ServiceContract
{
    /**
     * Get the workflow steps for a document type, optionally filtered by amount and department.
     *
     * @return list<object>
     */
    public function getSteps(
        string $documentType,
        ?int $amountCentavos = null,
        ?int $departmentId = null,
    ): array {
        $query = DB::table('approval_workflow_configs')
            ->where('document_type', $documentType)
            ->where('is_active', true);

        // Filter by amount threshold — include steps where threshold is null OR amount >= threshold
        if ($amountCentavos !== null) {
            $query->where(function ($q) use ($amountCentavos): void {
                $q->whereNull('amount_threshold_centavos')
                    ->orWhere('amount_threshold_centavos', '<=', $amountCentavos);
            });
        }

        // Filter by department — include steps where department_id is null OR matches
        if ($departmentId !== null) {
            $query->where(function ($q) use ($departmentId): void {
                $q->whereNull('department_id')
                    ->orWhere('department_id', $departmentId);
            });
        }

        return $query->orderBy('step_order')->get()->all();
    }

    /**
     * Get the next approval step based on the current document status.
     *
     * @return object|null The next step config, or null if no more steps (fully approved)
     */
    public function getNextStep(
        string $documentType,
        string $currentStatus,
        ?int $amountCentavos = null,
        ?int $departmentId = null,
    ): ?object {
        $steps = $this->getSteps($documentType, $amountCentavos, $departmentId);

        // Find the current step by matching target_status to current status
        $currentStepIndex = -1;
        foreach ($steps as $i => $step) {
            if ($step->target_status === $currentStatus) {
                $currentStepIndex = $i;
                break;
            }
        }

        // Return the next step (or first step if we're at the initial status)
        $nextIndex = $currentStepIndex + 1;

        return $steps[$nextIndex] ?? null;
    }

    /**
     * Validate that a user can approve at a given step.
     *
     * @throws SodViolationException|DomainException
     */
    public function validateApprover(
        object $stepConfig,
        User $approver,
        Model $document,
    ): void {
        // Check permission
        if (! $approver->hasPermissionTo($stepConfig->required_permission)) {
            throw new DomainException(
                "User does not have permission '{$stepConfig->required_permission}' required for this approval step.",
                'APPROVAL_PERMISSION_DENIED',
                403,
            );
        }

        // SoD: approver cannot be creator
        if ($stepConfig->sod_with_creator) {
            $creatorId = $document->created_by_id ?? $document->submitted_by ?? $document->requested_by ?? null;
            if ($creatorId !== null && (int) $creatorId === $approver->id) {
                throw new SodViolationException(
                    $stepConfig->document_type,
                    $stepConfig->step_name,
                    'Approver cannot be the same user who created/submitted this record (SoD).',
                );
            }
        }

        // SoD: approver cannot be previous step approver
        if ($stepConfig->sod_with_previous_step) {
            $lastApproval = ApprovalLog::where('approvable_type', get_class($document))
                ->where('approvable_id', $document->getKey())
                ->where('action', 'approved')
                ->latest()
                ->first();

            if ($lastApproval !== null && $lastApproval->user_id === $approver->id) {
                throw new SodViolationException(
                    $stepConfig->document_type,
                    $stepConfig->step_name,
                    'Approver cannot be the same user who approved the previous step (SoD).',
                );
            }
        }
    }

    /**
     * Get eligible approvers for a specific step (users with the required permission).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function getEligibleApprovers(object $stepConfig, ?int $departmentId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = User::permission($stepConfig->required_permission);

        if ($departmentId !== null) {
            $query->whereHas('departments', fn ($q) => $q->where('departments.id', $departmentId));
        }

        return $query->get();
    }
}
