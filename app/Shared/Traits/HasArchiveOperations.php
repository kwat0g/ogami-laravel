<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Models\User;
use App\Shared\Exceptions\DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Provides consistent archive (soft-delete), restore, and force-delete
 * operations for domain services.
 *
 * Usage: `use HasArchiveOperations;` in any service that manages a model
 * with the SoftDeletes trait. Override `dependentRelationships()` to define
 * FK integrity checks (Rule 7).
 *
 * Every action writes to the owen-it/laravel-auditing `audits` table
 * automatically via model events.
 */
trait HasArchiveOperations
{
    // ── Archive (Soft Delete) ───────────────────────────────────────────────

    /**
     * Soft-delete a record after checking for active dependent records.
     *
     * @throws DomainException if active children exist (CANNOT_ARCHIVE_HAS_DEPENDENTS)
     */
    protected function archiveRecord(Model $model, User $user): void
    {
        /** @var Model&SoftDeletes $model */
        DB::transaction(function () use ($model, $user): void {
            $this->assertNoDependentActiveRecords($model);
            $model->delete(); // SoftDeletes trait sets deleted_at = now()
        });
    }

    // ── Restore ─────────────────────────────────────────────────────────────

    /**
     * Restore a soft-deleted record from the archive.
     */
    protected function restoreRecord(string $modelClass, int|string $id, User $user): Model
    {
        return DB::transaction(function () use ($modelClass, $id, $user): Model {
            /** @var Model&SoftDeletes $model */
            $model = $modelClass::onlyTrashed()->findOrFail($id);
            $model->restore();

            return $model;
        });
    }

    // ── Permanent Delete (superadmin only) ──────────────────────────────────

    /**
     * Permanently remove a soft-deleted record from the database.
     * Must only be called after authorization check (superadmin).
     */
    protected function forceDeleteRecord(string $modelClass, int|string $id, User $user): void
    {
        DB::transaction(function () use ($modelClass, $id, $user): void {
            /** @var Model&SoftDeletes $model */
            $model = $modelClass::onlyTrashed()->findOrFail($id);
            $model->forceDelete();
        });
    }

    // ── List Archived ───────────────────────────────────────────────────────

    /**
     * Return a paginated list of soft-deleted (archived) records.
     *
     * @param  class-string<Model&SoftDeletes>  $modelClass
     */
    protected function listArchivedRecords(
        string $modelClass,
        int $perPage = 20,
        ?string $search = null,
        array $searchColumns = ['id'],
    ): LengthAwarePaginator {
        $query = $modelClass::onlyTrashed();

        if ($search && count($searchColumns) > 0) {
            $query->where(function ($q) use ($search, $searchColumns): void {
                foreach ($searchColumns as $col) {
                    $q->orWhere($col, 'ilike', "%{$search}%");
                }
            });
        }

        return $query->latest('deleted_at')->paginate($perPage);
    }

    // ── FK Integrity Check (Rule 7) ─────────────────────────────────────────

    /**
     * Block archiving if the model has active (non-deleted) dependent records.
     *
     * @throws DomainException
     */
    protected function assertNoDependentActiveRecords(Model $model): void
    {
        $blockers = [];

        foreach ($this->dependentRelationships($model) as $relation => $label) {
            $relQuery = $model->{$relation}();

            // If the related model uses SoftDeletes, only count non-deleted children.
            // Otherwise, just check for any existing children.
            if (method_exists($relQuery->getRelated(), 'getDeletedAtColumn')) {
                if ($relQuery->whereNull($relQuery->getRelated()->getQualifiedDeletedAtColumn())->exists()) {
                    $blockers[] = $label;
                }
            } elseif ($relQuery->exists()) {
                $blockers[] = $label;
            }
        }

        if (! empty($blockers)) {
            throw new DomainException(
                message: 'Cannot archive this record because it has active: ' . implode(', ', $blockers) . '.',
                errorCode: 'CANNOT_ARCHIVE_HAS_DEPENDENTS',
                httpStatus: 409,
                context: ['blockers' => $blockers],
            );
        }
    }

    /**
     * Override in each service to specify which relationships block archiving.
     *
     * Return format: ['relationMethod' => 'Human Label']
     *
     * Example:
     *   return ['employees' => 'Employees', 'invoices' => 'Invoices'];
     *
     * @return array<string, string>
     */
    protected function dependentRelationships(Model $model): array
    {
        return [];
    }
}
