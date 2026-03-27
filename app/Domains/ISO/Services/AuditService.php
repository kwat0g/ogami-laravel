<?php

declare(strict_types=1);

namespace App\Domains\ISO\Services;

use App\Domains\ISO\Models\AuditFinding;
use App\Domains\ISO\Models\ImprovementAction;
use App\Domains\ISO\Models\InternalAudit;
use App\Events\ISO\AuditFindingCreated;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Audit Service — manages internal audits, findings, and corrective actions.
 *
 * Decomposed from the monolithic ISOService.
 */
final class AuditService implements ServiceContract
{
    /** @param array<string,mixed> $filters */
    public function paginateAudits(array $filters = []): LengthAwarePaginator
    {
        return InternalAudit::query()
            ->when($filters['with_archived'] ?? false, fn ($q) => $q->withTrashed())
            ->with(['leadAuditor'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->whereYear('audit_date', $v))
            ->orderByDesc('audit_date')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /** @param array<string,mixed> $data */
    public function storeAudit(array $data, int $userId): InternalAudit
    {
        return InternalAudit::create([
            'audit_scope' => $data['audit_scope'],
            'standard' => $data['standard'] ?? 'ISO 9001:2015',
            'lead_auditor_id' => $data['lead_auditor_id'] ?? null,
            'audit_date' => $data['audit_date'],
            'status' => 'planned',
            'created_by_id' => $userId,
        ]);
    }

    public function startAudit(InternalAudit $audit): InternalAudit
    {
        if ($audit->status !== 'planned') {
            throw new DomainException(
                'Audit must be in planned status to start.',
                'ISO_AUDIT_NOT_PLANNED',
                422,
            );
        }

        $audit->update(['status' => 'in_progress']);

        return $audit;
    }

    public function completeAudit(InternalAudit $audit, ?string $summary): InternalAudit
    {
        if ($audit->status !== 'in_progress') {
            throw new DomainException(
                'Audit must be in progress to complete.',
                'ISO_AUDIT_NOT_IN_PROGRESS',
                422,
            );
        }

        $audit->update(['status' => 'completed', 'summary' => $summary]);

        return $audit;
    }

    // ── Findings ──────────────────────────────────────────────────────────

    /** @param array<string,mixed> $data */
    public function storeFinding(InternalAudit $audit, array $data, int $userId): AuditFinding
    {
        $finding = $audit->findings()->create([
            'finding_type' => $data['finding_type'] ?? 'observation',
            'clause_ref' => $data['clause_ref'] ?? null,
            'description' => $data['description'],
            'severity' => $data['severity'] ?? 'minor',
            'status' => 'open',
            'raised_by_id' => $userId,
        ]);

        AuditFindingCreated::dispatch($finding);

        return $finding;
    }

    public function closeFinding(AuditFinding $finding): AuditFinding
    {
        if ($finding->status === 'closed') {
            throw new DomainException(
                'Finding is already closed.',
                'ISO_FINDING_ALREADY_CLOSED',
                422,
            );
        }

        $finding->update(['status' => 'closed']);

        return $finding;
    }

    /**
     * Open findings aging — how long each finding has been open.
     *
     * @return Collection<int, array{finding_id: int, description: string, severity: string, days_open: int, audit_scope: string}>
     */
    public function openFindingsAging(): Collection
    {
        return AuditFinding::query()
            ->where('status', 'open')
            ->with('audit')
            ->orderBy('created_at')
            ->get()
            ->map(fn (AuditFinding $f) => [
                'finding_id' => $f->id,
                'description' => $f->description,
                'severity' => $f->severity,
                'days_open' => (int) $f->created_at->diffInDays(now()),
                'audit_scope' => $f->audit?->audit_scope ?? '—',
            ]);
    }

    /**
     * Audit statistics summary.
     *
     * @return array{total_audits: int, open_findings: int, closed_findings: int, overdue_actions: int}
     */
    public function statistics(?int $year = null): array
    {
        $auditCount = InternalAudit::query()
            ->when($year, fn ($q, $y) => $q->whereYear('audit_date', $y))
            ->count();

        $openFindings = AuditFinding::where('status', 'open')->count();
        $closedFindings = AuditFinding::where('status', 'closed')->count();

        $overdueActions = ImprovementAction::query()
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        return [
            'total_audits' => $auditCount,
            'open_findings' => $openFindings,
            'closed_findings' => $closedFindings,
            'overdue_actions' => $overdueActions,
        ];
    }
}
