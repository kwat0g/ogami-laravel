<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

use App\Models\User;
use App\Shared\Models\ApprovalLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait for models that go through a multi-step approval workflow.
 *
 * Provides:
 * - `approvalLogs()` morphMany relationship
 * - `logApproval()` helper to record an approval action
 * - `latestApprovalAt()` to get the most recent approval stage
 * - `wasApprovedBy()` to check if a specific user approved at a stage
 *
 * Usage:
 *   1. Add `use HasApprovalWorkflow;` to your model
 *   2. The model's table must have a `status` column
 *   3. Call `$model->logApproval('stage_name', 'approved', $user)` in your service
 *
 * The trait does NOT enforce which approval stages exist — that's the responsibility
 * of the domain's StateMachine class. This trait only records the audit trail.
 */
trait HasApprovalWorkflow
{
    // ── Relationship ─────────────────────────────────────────────────────

    /**
     * All approval log entries for this model, newest first.
     */
    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class, 'approvable')
            ->orderByDesc('created_at');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Record an approval action (approved, rejected, returned, etc.).
     *
     * @param string $stage   The workflow step (e.g., 'head_noted', 'manager_checked')
     * @param string $action  The action taken (e.g., 'approved', 'rejected', 'returned')
     * @param User   $user    The user performing the action
     * @param string|null $remarks  Optional remarks/comments
     * @param array|null $metadata  Optional extra data (e.g., old status, new status)
     */
    public function logApproval(
        string $stage,
        string $action,
        User $user,
        ?string $remarks = null,
        ?array $metadata = null,
    ): ApprovalLog {
        return $this->approvalLogs()->create([
            'stage' => $stage,
            'action' => $action,
            'user_id' => $user->id,
            'remarks' => $remarks,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get the most recent approval log entry.
     */
    public function latestApproval(): ?ApprovalLog
    {
        return $this->approvalLogs()->first();
    }

    /**
     * Check whether a specific user performed an action at a given stage.
     */
    public function wasApprovedBy(User $user, string $stage): bool
    {
        return $this->approvalLogs()
            ->where('user_id', $user->id)
            ->where('stage', $stage)
            ->where('action', 'approved')
            ->exists();
    }

    /**
     * Get all approval logs for a specific stage.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ApprovalLog>
     */
    public function approvalsAtStage(string $stage): \Illuminate\Database\Eloquent\Collection
    {
        return $this->approvalLogs()
            ->where('stage', $stage)
            ->get();
    }

    /**
     * Get the approval timeline as an array suitable for frontend display.
     *
     * @return list<array{stage: string, action: string, user_id: int, user_name: string|null, remarks: string|null, created_at: string}>
     */
    public function approvalTimeline(): array
    {
        return $this->approvalLogs()
            ->with('user:id,name')
            ->get()
            ->map(fn (ApprovalLog $log) => [
                'stage' => $log->stage,
                'action' => $log->action,
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name,
                'remarks' => $log->remarks,
                'created_at' => $log->created_at->toIso8601String(),
            ])
            ->all();
    }
}
