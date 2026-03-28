<?php

declare(strict_types=1);

namespace App\Domains\QC\Services;

use App\Domains\QC\Models\CapaAction;
use App\Domains\QC\Models\NonConformanceReport;
use App\Events\QC\NonConformanceReportRaised;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class NcrService implements ServiceContract
{
    /** @param array<string,mixed> $params */
    public function paginate(array $params = []): LengthAwarePaginator
    {
        return NonConformanceReport::query()
            ->when($params['with_archived'] ?? false, fn ($q) => $q->withTrashed())
            ->when($params['search'] ?? null, fn ($q, $v) => $q->where(fn ($q2) => $q2->where('ncr_reference', 'ilike', "%{$v}%")->orWhere('title', 'ilike', "%{$v}%")))
            ->when($params['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($params['severity'] ?? null, fn ($q, $v) => $q->where('severity', $v))
            ->with(['inspection.itemMaster', 'raisedBy', 'capaActions'])
            ->orderByDesc('created_at')
            ->paginate((int) ($params['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, int $userId): NonConformanceReport
    {
        return DB::transaction(function () use ($data, $userId) {
            /** @var NonConformanceReport $ncr */
            $ncr = NonConformanceReport::create([
                'inspection_id' => $data['inspection_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'severity' => $data['severity'] ?? 'minor',
                'raised_by_id' => $userId,
            ]);

            // Transition inspection to on_hold
            $ncr->inspection()->update(['status' => 'on_hold']);

            $loaded = $ncr->load(['inspection.itemMaster', 'raisedBy']);

            // Fire AFTER the transaction commits so the queued listener
            // (CreateCapaOnNcrRaised) reads the committed NCR row.
            DB::afterCommit(fn () => event(new NonConformanceReportRaised($loaded->fresh(['inspection.itemMaster', 'raisedBy']))));

            return $loaded;
        });
    }

    /** @param array<string,mixed> $data */
    public function issueCapa(NonConformanceReport $ncr, array $data, int $userId): CapaAction
    {
        if (! in_array($ncr->status, ['open', 'under_review'], true)) {
            throw new DomainException(
                message: "NCR cannot accept a CAPA in its current status '{$ncr->status}'.",
                errorCode: 'QC_NCR_CANNOT_ISSUE_CAPA',
                httpStatus: 422,
            );
        }

        return DB::transaction(function () use ($ncr, $data, $userId) {
            /** @var CapaAction $capa */
            $capa = CapaAction::create([
                'ncr_id' => $ncr->id,
                'type' => $data['type'] ?? 'corrective',
                'description' => $data['description'],
                'due_date' => $data['due_date'],
                'assigned_to_id' => $data['assigned_to_id'] ?? null,
                'created_by_id' => $userId,
            ]);

            $ncr->update(['status' => 'capa_issued']);

            return $capa;
        });
    }

    public function closeNcr(NonConformanceReport $ncr, int $userId): NonConformanceReport
    {
        if ($ncr->status === 'closed') {
            throw new DomainException(
                message: 'NCR is already closed.',
                errorCode: 'QC_NCR_ALREADY_CLOSED',
                httpStatus: 422,
            );
        }

        $ncr->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by_id' => $userId,
        ]);

        return $ncr;
    }

    public function completeCapaAction(CapaAction $capa): CapaAction
    {
        if ($capa->status === 'completed' || $capa->status === 'verified') {
            throw new DomainException(
                message: 'CAPA action is already completed or verified.',
                errorCode: 'QC_CAPA_ALREADY_COMPLETED',
                httpStatus: 422,
            );
        }

        $capa->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $capa;
    }
}
