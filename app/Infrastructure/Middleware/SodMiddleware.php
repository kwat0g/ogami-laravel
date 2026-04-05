<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use App\Domains\Accounting\Models\JournalEntry;
use App\Shared\Exceptions\SodViolationException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Segregation of Duties (SoD) middleware.
 *
 * Reads the `sod_conflict_matrix` from the system_settings table and
 * blocks any user who tries to perform an action that conflicts with
 * a role/permission they already hold on the same process.
 *
 * The setting value (JSONB) is expected to look like:
 * {
 *   "payroll": {
 *     "prepare":  ["approve"],
 *     "approve":  ["prepare", "release"],
 *     "release":  ["approve"]
 *   },
 *   "procurement": {
 *     "request":  ["approve"],
 *     "approve":  ["request", "receive"]
 *   }
 * }
 *
 * Usage in routes:
 *   Route::middleware('sod:payroll,approve')
 *
 * Bypassed for:
 *   - admin role (operational override)
 *   - unauthenticated requests (handled by auth middleware first)
 *
 * NOTE: `manager` is NO LONGER bypassed. Department-specific manager sub-roles
 * (hr_manager, finance_manager, ops_manager) have SoD constraints per the v1.0
 * Role & Permission Matrix (Feb 2026). Record-level checks are in Policies;
 * role-level checks are enforced here via the conflict matrix.
 */
class SodMiddleware
{
    public function handle(Request $request, Closure $next, string $process, string $action): Response
    {
        $user = $request->user();

        if (! $user || $user->hasAnyRole(['admin', 'super_admin'])) {
            return $next($request);
        }

        if ($process === 'journal_entries' && $action === 'post') {
            $journalEntry = $request->route('journalEntry');

            if (
                $journalEntry instanceof JournalEntry
                && $journalEntry->source_type !== 'manual'
                && $user->hasRole('accounting_manager')
            ) {
                return $next($request);
            }
        }

        $matrix = $this->loadMatrix();

        $conflicts = $matrix[$process][$action] ?? [];

        foreach ($conflicts as $conflictingAction) {
            $permission = "{$process}.{$conflictingAction}";

            if ($user->can($permission)) {
                $this->logViolation($request, $user->id, $process, $action, $conflictingAction);

                throw new SodViolationException(
                    processName: $process,
                    conflictingAction: $conflictingAction,
                );
            }
        }

        return $next($request);
    }

    /**
     * Load the SoD matrix from system_settings.
     * Cached in Laravel's cache store for 5 minutes so DB changes propagate
     * quickly without hammering the DB on every request.
     *
     * @return array<string, array<string, list<string>>>
     */
    private function loadMatrix(): array
    {
        /** @var array<string, array<string, list<string>>> */
        return Cache::remember(
            'sod_conflict_matrix',
            300, // 5 minutes
            function (): array {
                $row = DB::table('system_settings')
                    ->where('key', 'sod_conflict_matrix')
                    ->value('value');

                return $row !== null ? (array) json_decode((string) $row, true) : [];
            },
        );
    }

    /**
     * Write an audit trail entry whenever a SoD violation is detected.
     * Inserts directly into the `audits` table (owen-it/laravel-auditing schema).
     */
    private function logViolation(
        Request $request,
        int $userId,
        string $process,
        string $action,
        string $conflictingAction,
    ): void {
        $now = now()->toDateTimeString();

        try {
            DB::table('audits')->insert([
                'user_type' => 'App\\Models\\User',
                'user_id' => $userId,
                'event' => 'sod_violation',
                'auditable_type' => 'sod_check',
                'auditable_id' => 0,
                'old_values' => json_encode(['process' => $process, 'action' => $action]),
                'new_values' => json_encode(['conflicting_action' => $conflictingAction]),
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ?? '',
                'tags' => 'sod_violation',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            // Non-fatal — audit write failure must never block the SoD throw
            Log::error('SoD audit write failed', [
                'user_id' => $userId,
                'process' => $process,
                'action' => $action,
                'conflicting_action' => $conflictingAction,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
