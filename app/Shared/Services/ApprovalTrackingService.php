<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * REC-16: Approval Tracking Service -- provides multi-step workflow visibility.
 *
 * Initializes and updates tracking entries for approval workflows so users
 * can see which step is pending, who approved what, and when. Used by
 * AP Invoices, Leave Requests, Loans, and Payroll Runs.
 */
final class ApprovalTrackingService implements ServiceContract
{
    /**
     * Initialize tracking entries when a workflow starts.
     *
     * @param string $type Entity table name (vendor_invoices, leave_requests, etc.)
     * @param int $id Entity ID
     * @param list<array{step_name: string, step_label: string}> $steps Ordered approval steps
     */
    public function initializeTracking(string $type, int $id, array $steps): void
    {
        // Clear any existing tracking for this entity (e.g., on re-submission)
        DB::table('approval_tracking')
            ->where('trackable_type', $type)
            ->where('trackable_id', $id)
            ->delete();

        foreach ($steps as $order => $step) {
            DB::table('approval_tracking')->insert([
                'trackable_type' => $type,
                'trackable_id' => $id,
                'step_order' => $order + 1,
                'step_name' => $step['step_name'],
                'step_label' => $step['step_label'],
                'status' => 'pending',
                'completed_by_id' => null,
                'completed_at' => null,
                'comments' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Mark a step as completed.
     */
    public function completeStep(
        string $type,
        int $id,
        string $stepName,
        int $actorId,
        ?string $comments = null,
    ): void {
        DB::table('approval_tracking')
            ->where('trackable_type', $type)
            ->where('trackable_id', $id)
            ->where('step_name', $stepName)
            ->update([
                'status' => 'completed',
                'completed_by_id' => $actorId,
                'completed_at' => now(),
                'comments' => $comments,
                'updated_at' => now(),
            ]);
    }

    /**
     * Mark a step as returned (sent back for revision).
     */
    public function returnStep(
        string $type,
        int $id,
        string $stepName,
        int $actorId,
        ?string $comments = null,
    ): void {
        DB::table('approval_tracking')
            ->where('trackable_type', $type)
            ->where('trackable_id', $id)
            ->where('step_name', $stepName)
            ->update([
                'status' => 'returned',
                'completed_by_id' => $actorId,
                'completed_at' => now(),
                'comments' => $comments,
                'updated_at' => now(),
            ]);
    }

    /**
     * Get the full progress for a trackable entity.
     *
     * @return list<array{step_order: int, step_name: string, step_label: string, status: string, completed_by_id: int|null, completed_at: string|null, comments: string|null}>
     */
    public function getProgress(string $type, int $id): array
    {
        return DB::table('approval_tracking')
            ->where('trackable_type', $type)
            ->where('trackable_id', $id)
            ->orderBy('step_order')
            ->get()
            ->map(fn ($row) => [
                'step_order' => $row->step_order,
                'step_name' => $row->step_name,
                'step_label' => $row->step_label,
                'status' => $row->status,
                'completed_by_id' => $row->completed_by_id,
                'completed_at' => $row->completed_at,
                'comments' => $row->comments,
            ])
            ->toArray();
    }
}
