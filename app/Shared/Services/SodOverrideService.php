<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Models\ApprovalDelegate;
use App\Shared\Models\SodOverrideAuditLog;
use Illuminate\Support\Facades\Log;

/**
 * REC-01: SoD Override Service -- manages emergency overrides and delegation.
 *
 * When SoD rules create deadlocks (e.g., only one HR manager who also
 * initiated the payroll run), this service provides a controlled escape
 * hatch with full audit trail.
 *
 * Usage:
 *   1. Admin grants override via grantOverride()
 *   2. Approval service checks hasValidOverride() before throwing SodViolationException
 *   3. If override exists and is valid, approval proceeds with audit log
 *   4. Override is marked as used after single use
 */
final class SodOverrideService implements ServiceContract
{
    /**
     * Grant an emergency SoD override for a specific entity and action.
     *
     * @param string $overrideType e.g. 'payroll_hr_approve', 'ap_head_note'
     * @param string $entityType e.g. 'payroll_runs', 'vendor_invoices'
     * @param int $entityId ID of the specific record
     * @param int $originalActorId User who was blocked by SoD
     * @param int $grantedById Admin/super_admin who authorizes the override
     * @param string $reason Mandatory justification
     * @param int $expiresInHours Auto-expire after this many hours (default 24)
     */
    public function grantOverride(
        string $overrideType,
        string $entityType,
        int $entityId,
        int $originalActorId,
        int $grantedById,
        string $reason,
        int $expiresInHours = 24,
    ): SodOverrideAuditLog {
        $override = SodOverrideAuditLog::create([
            'override_type' => $overrideType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'original_actor_id' => $originalActorId,
            'granted_by_id' => $grantedById,
            'reason' => $reason,
            'granted_at' => now(),
            'expires_at' => now()->addHours($expiresInHours),
            'was_used' => false,
        ]);

        Log::warning('SoD override granted', [
            'override_id' => $override->id,
            'type' => $overrideType,
            'entity' => "{$entityType}:{$entityId}",
            'actor' => $originalActorId,
            'granted_by' => $grantedById,
            'reason' => $reason,
            'expires_at' => $override->expires_at->toIso8601String(),
        ]);

        return $override;
    }

    /**
     * Check whether a valid (non-expired, unused) override exists.
     * If found and valid, marks it as used.
     */
    public function hasValidOverride(
        string $overrideType,
        string $entityType,
        int $entityId,
        int $actorId,
    ): bool {
        $override = SodOverrideAuditLog::where('override_type', $overrideType)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('original_actor_id', $actorId)
            ->where('was_used', false)
            ->where('expires_at', '>', now())
            ->latest('granted_at')
            ->first();

        if (! $override) {
            return false;
        }

        // Mark as used -- single use only
        $override->update(['was_used' => true]);

        Log::warning('SoD override consumed', [
            'override_id' => $override->id,
            'type' => $overrideType,
            'entity' => "{$entityType}:{$entityId}",
            'actor' => $actorId,
        ]);

        return true;
    }

    /**
     * Find an active delegate for a given permission scope.
     *
     * @param string $permissionScope e.g. 'payroll.hr_approve'
     * @param int|null $excludeUserId User to exclude (the person who needs delegation)
     */
    public function findDelegate(string $permissionScope, ?int $excludeUserId = null): ?User
    {
        $today = now()->toDateString();

        $delegation = ApprovalDelegate::where('permission_scope', $permissionScope)
            ->where('effective_from', '<=', $today)
            ->where('effective_until', '>=', $today)
            ->when($excludeUserId, fn ($q) => $q->where('delegate_id', '!=', $excludeUserId))
            ->with('delegate')
            ->first();

        return $delegation?->delegate;
    }

    /**
     * Create a delegation record (e.g., before VP goes on leave).
     */
    public function createDelegation(
        int $delegatorId,
        int $delegateId,
        string $permissionScope,
        string $effectiveFrom,
        string $effectiveUntil,
        string $reason,
        int $createdById,
    ): ApprovalDelegate {
        $delegate = ApprovalDelegate::create([
            'delegator_id' => $delegatorId,
            'delegate_id' => $delegateId,
            'permission_scope' => $permissionScope,
            'effective_from' => $effectiveFrom,
            'effective_until' => $effectiveUntil,
            'reason' => $reason,
            'created_by_id' => $createdById,
        ]);

        Log::info('Approval delegation created', [
            'delegation_id' => $delegate->id,
            'delegator' => $delegatorId,
            'delegate' => $delegateId,
            'scope' => $permissionScope,
            'from' => $effectiveFrom,
            'until' => $effectiveUntil,
        ]);

        return $delegate;
    }
}
