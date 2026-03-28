<?php

declare(strict_types=1);

namespace App\Domains\ISO\Services;

use App\Domains\ISO\Models\ControlledDocument;
use App\Domains\ISO\Models\DocumentDistribution;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Document Acknowledgment Service — Item 45.
 *
 * Tracks read acknowledgment for distributed ISO controlled documents.
 * When a document is distributed, recipients must acknowledge they have
 * read and understood it. Overdue acknowledgments are flagged.
 *
 * ISO 9001 requirement: training records linked to procedure changes.
 */
final class DocumentAcknowledgmentService implements ServiceContract
{
    /**
     * Record that a user has read and acknowledged a distributed document.
     */
    public function acknowledge(int $distributionId, User $user): DocumentDistribution
    {
        $distribution = DocumentDistribution::find($distributionId);

        if ($distribution === null) {
            throw new DomainException('Distribution record not found.', 'ISO_DIST_NOT_FOUND', 404);
        }

        if ($distribution->user_id !== $user->id) {
            throw new DomainException('You can only acknowledge documents distributed to you.', 'ISO_NOT_YOUR_DOCUMENT', 403);
        }

        if ($distribution->acknowledged_at !== null) {
            return $distribution; // Already acknowledged — idempotent
        }

        $distribution->update([
            'acknowledged_at' => now(),
            'acknowledged' => true,
        ]);

        return $distribution->fresh() ?? $distribution;
    }

    /**
     * Get acknowledgment status for a document (all distributions).
     *
     * @return array{document_id: int, title: string, total_distributed: int, acknowledged: int, pending: int, overdue: int, recipients: list<array>}
     */
    public function acknowledgmentStatus(ControlledDocument $document): array
    {
        $distributions = DocumentDistribution::where('controlled_document_id', $document->id)
            ->with('user')
            ->get();

        $deadline = $document->acknowledgment_deadline_days ?? 7;

        $recipients = $distributions->map(function (DocumentDistribution $dist) use ($deadline) {
            $isOverdue = $dist->acknowledged_at === null
                && $dist->created_at
                && now()->diffInDays($dist->created_at) > $deadline;

            return [
                'distribution_id' => $dist->id,
                'user_id' => $dist->user_id,
                'user_name' => $dist->user?->name ?? '—',
                'distributed_at' => $dist->created_at?->toIso8601String(),
                'acknowledged_at' => $dist->acknowledged_at?->toIso8601String(),
                'acknowledged' => $dist->acknowledged_at !== null,
                'overdue' => $isOverdue,
                'days_since_distribution' => $dist->created_at ? (int) now()->diffInDays($dist->created_at) : 0,
            ];
        });

        return [
            'document_id' => $document->id,
            'title' => $document->title,
            'total_distributed' => $distributions->count(),
            'acknowledged' => $recipients->where('acknowledged', true)->count(),
            'pending' => $recipients->where('acknowledged', false)->count(),
            'overdue' => $recipients->where('overdue', true)->count(),
            'completion_pct' => $distributions->count() > 0
                ? round(($recipients->where('acknowledged', true)->count() / $distributions->count()) * 100, 1)
                : 0,
            'recipients' => $recipients->toArray(),
        ];
    }

    /**
     * Get all documents with pending acknowledgments for a user.
     *
     * @return Collection<int, array>
     */
    public function pendingForUser(User $user): Collection
    {
        return DocumentDistribution::where('user_id', $user->id)
            ->whereNull('acknowledged_at')
            ->with('controlledDocument')
            ->get()
            ->map(fn (DocumentDistribution $dist) => [
                'distribution_id' => $dist->id,
                'document_id' => $dist->controlled_document_id,
                'document_title' => $dist->controlledDocument?->title ?? '—',
                'document_code' => $dist->controlledDocument?->document_code ?? '—',
                'distributed_at' => $dist->created_at?->toIso8601String(),
                'days_pending' => $dist->created_at ? (int) now()->diffInDays($dist->created_at) : 0,
            ]);
    }

    /**
     * Overdue acknowledgment report — all documents with pending acks past deadline.
     *
     * @return Collection<int, array>
     */
    public function overdueReport(int $deadlineDays = 7): Collection
    {
        $cutoff = now()->subDays($deadlineDays);

        return DocumentDistribution::whereNull('acknowledged_at')
            ->where('created_at', '<', $cutoff)
            ->with(['controlledDocument', 'user'])
            ->get()
            ->map(fn (DocumentDistribution $dist) => [
                'distribution_id' => $dist->id,
                'document_title' => $dist->controlledDocument?->title ?? '—',
                'user_name' => $dist->user?->name ?? '—',
                'distributed_at' => $dist->created_at?->toIso8601String(),
                'days_overdue' => (int) now()->diffInDays($dist->created_at) - $deadlineDays,
            ]);
    }
}
