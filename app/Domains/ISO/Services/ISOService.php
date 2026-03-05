<?php

declare(strict_types=1);

namespace App\Domains\ISO\Services;

use App\Domains\ISO\Models\AuditFinding;
use App\Domains\ISO\Models\ControlledDocument;
use App\Domains\ISO\Models\InternalAudit;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ISOService implements ServiceContract
{
    // ── Controlled Documents ──────────────────────────────────────────────

    public function paginateDocuments(array $filters = []): LengthAwarePaginator
    {
        return ControlledDocument::query()
            ->with(['owner'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['document_type'] ?? null, fn ($q, $v) => $q->where('document_type', $v))
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    public function storeDocument(array $data, int $userId): ControlledDocument
    {
        return ControlledDocument::create([
            'title'           => $data['title'],
            'category'        => $data['category'] ?? null,
            'document_type'   => $data['document_type'] ?? 'procedure',
            'owner_id'        => $data['owner_id'] ?? $userId,
            'current_version' => $data['current_version'] ?? '1.0',
            'status'          => 'draft',
            'effective_date'  => $data['effective_date'] ?? null,
            'review_date'     => $data['review_date'] ?? null,
            'is_active'       => true,
            'created_by_id'   => $userId,
        ]);
    }

    public function updateDocument(ControlledDocument $doc, array $data): ControlledDocument
    {
        $doc->update(array_filter($data, fn ($v) => $v !== null));
        return $doc;
    }

    // ── Internal Audits ───────────────────────────────────────────────────

    public function paginateAudits(array $filters = []): LengthAwarePaginator
    {
        return InternalAudit::query()
            ->with(['leadAuditor'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('audit_date')
            ->paginate(25);
    }

    public function storeAudit(array $data, int $userId): InternalAudit
    {
        return InternalAudit::create([
            'audit_scope'     => $data['audit_scope'],
            'standard'        => $data['standard'] ?? 'ISO 9001:2015',
            'lead_auditor_id' => $data['lead_auditor_id'] ?? null,
            'audit_date'      => $data['audit_date'],
            'status'          => 'planned',
            'created_by_id'   => $userId,
        ]);
    }

    public function startAudit(InternalAudit $audit): InternalAudit
    {
        if ($audit->status !== 'planned') {
            throw new DomainException('ISO_AUDIT_NOT_PLANNED');
        }
        $audit->update(['status' => 'in_progress']);
        return $audit;
    }

    public function completeAudit(InternalAudit $audit, ?string $summary): InternalAudit
    {
        if ($audit->status !== 'in_progress') {
            throw new DomainException('ISO_AUDIT_NOT_IN_PROGRESS');
        }
        $audit->update(['status' => 'completed', 'summary' => $summary]);
        return $audit;
    }

    // ── Audit Findings ────────────────────────────────────────────────────

    public function storeFinding(InternalAudit $audit, array $data, int $userId): AuditFinding
    {
        return $audit->findings()->create([
            'finding_type' => $data['finding_type'] ?? 'observation',
            'clause_ref'   => $data['clause_ref'] ?? null,
            'description'  => $data['description'],
            'severity'     => $data['severity'] ?? 'minor',
            'status'       => 'open',
            'raised_by_id' => $userId,
        ]);
    }
}
