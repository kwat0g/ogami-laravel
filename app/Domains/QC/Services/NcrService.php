<?php

declare(strict_types=1);

namespace App\Domains\QC\Services;

use App\Domains\QC\Models\CapaAction;
use App\Domains\QC\Models\NonConformanceReport;
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
                'title'         => $data['title'],
                'description'   => $data['description'],
                'severity'      => $data['severity'] ?? 'minor',
                'raised_by_id'  => $userId,
            ]);

            // Transition inspection to on_hold
            $ncr->inspection()->update(['status' => 'on_hold']);

            // Update NCR status on the parent inspection
            $ncr->inspection()->update(['status' => 'on_hold']);

            return $ncr->load(['inspection.itemMaster', 'raisedBy']);
        });
    }

    /** @param array<string,mixed> $data */
    public function issueCapa(NonConformanceReport $ncr, array $data, int $userId): CapaAction
    {
        if (!in_array($ncr->status, ['open', 'under_review'], true)) {
            throw new DomainException('QC_NCR_CANNOT_ISSUE_CAPA');
        }

        return DB::transaction(function () use ($ncr, $data, $userId) {
            /** @var CapaAction $capa */
            $capa = CapaAction::create([
                'ncr_id'        => $ncr->id,
                'type'          => $data['type'] ?? 'corrective',
                'description'   => $data['description'],
                'due_date'      => $data['due_date'],
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
            throw new DomainException('QC_NCR_ALREADY_CLOSED');
        }

        $ncr->update([
            'status'       => 'closed',
            'closed_at'    => now(),
            'closed_by_id' => $userId,
        ]);

        return $ncr;
    }

    public function completeCapaAction(CapaAction $capa): CapaAction
    {
        if ($capa->status === 'completed' || $capa->status === 'verified') {
            throw new DomainException('QC_CAPA_ALREADY_COMPLETED');
        }

        $capa->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        return $capa;
    }
}
