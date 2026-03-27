<?php

declare(strict_types=1);

namespace App\Domains\ISO\Services;

use App\Domains\ISO\Models\ControlledDocument;
use App\Domains\ISO\Models\DocumentRevision;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Document Control Service — manages ISO controlled documents and their revision lifecycle.
 *
 * Workflow: draft → under_review → approved → obsolete
 *
 * Decomposed from the monolithic ISOService.
 */
final class DocumentControlService implements ServiceContract
{
    /** @param array<string,mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return ControlledDocument::query()
            ->when($filters['with_archived'] ?? false, fn ($q) => $q->withTrashed())
            ->with(['owner'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['document_type'] ?? null, fn ($q, $v) => $q->where('document_type', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('title', 'ilike', "%{$v}%"))
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, int $userId): ControlledDocument
    {
        return ControlledDocument::create([
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'document_type' => $data['document_type'] ?? 'procedure',
            'owner_id' => $data['owner_id'] ?? $userId,
            'current_version' => $data['current_version'] ?? '1.0',
            'status' => 'draft',
            'effective_date' => $data['effective_date'] ?? null,
            'review_date' => $data['review_date'] ?? null,
            'is_active' => true,
            'created_by_id' => $userId,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function update(ControlledDocument $doc, array $data): ControlledDocument
    {
        if ($doc->status === 'approved') {
            throw new DomainException(
                'Approved documents cannot be edited directly. Create a new revision instead.',
                'ISO_DOC_APPROVED_READONLY',
                422,
            );
        }

        $doc->update(array_filter($data, fn ($v) => $v !== null));

        return $doc;
    }

    public function submitForReview(ControlledDocument $doc): ControlledDocument
    {
        if ($doc->status !== 'draft') {
            throw new DomainException(
                'Document must be in draft to submit for review.',
                'ISO_DOC_NOT_DRAFT',
                422,
            );
        }

        $doc->update(['status' => 'under_review']);

        return $doc;
    }

    public function approve(ControlledDocument $doc, int $userId): ControlledDocument
    {
        if ($doc->status !== 'under_review') {
            throw new DomainException(
                'Document must be under review to approve.',
                'ISO_DOC_NOT_UNDER_REVIEW',
                422,
            );
        }

        return DB::transaction(function () use ($doc, $userId): ControlledDocument {
            $doc->update(['status' => 'approved']);

            DocumentRevision::create([
                'controlled_document_id' => $doc->id,
                'version' => $doc->current_version,
                'change_summary' => 'Document approved.',
                'revised_by_id' => $doc->created_by_id,
                'approved_by_id' => $userId,
                'approved_at' => now(),
            ]);

            return $doc->fresh();
        });
    }

    public function obsolete(ControlledDocument $doc): ControlledDocument
    {
        if (! in_array($doc->status, ['approved', 'under_review'], true)) {
            throw new DomainException(
                'Only approved or under-review documents can be obsoleted.',
                'ISO_DOC_CANNOT_OBSOLETE',
                422,
            );
        }

        $doc->update(['status' => 'obsolete', 'is_active' => false]);

        return $doc;
    }

    /**
     * Create a new revision of an approved document (creates draft copy at next version).
     */
    public function createRevision(ControlledDocument $doc, string $changeSummary, int $userId): ControlledDocument
    {
        if ($doc->status !== 'approved') {
            throw new DomainException(
                'Only approved documents can be revised.',
                'ISO_DOC_NOT_APPROVED',
                422,
            );
        }

        return DB::transaction(function () use ($doc, $changeSummary, $userId): ControlledDocument {
            // Bump version
            $parts = explode('.', $doc->current_version);
            $major = (int) ($parts[0] ?? 1);
            $minor = (int) ($parts[1] ?? 0);
            $newVersion = $major . '.' . ($minor + 1);

            $doc->update([
                'current_version' => $newVersion,
                'status' => 'draft',
            ]);

            DocumentRevision::create([
                'controlled_document_id' => $doc->id,
                'version' => $newVersion,
                'change_summary' => $changeSummary,
                'revised_by_id' => $userId,
            ]);

            return $doc->fresh();
        });
    }

    /**
     * Documents due for review within the next N days.
     *
     * @return Collection<int, ControlledDocument>
     */
    public function dueForReview(int $daysAhead = 30): Collection
    {
        return ControlledDocument::query()
            ->where('status', 'approved')
            ->where('is_active', true)
            ->whereNotNull('review_date')
            ->where('review_date', '<=', now()->addDays($daysAhead))
            ->with('owner')
            ->orderBy('review_date')
            ->get();
    }
}
