<?php

declare(strict_types=1);

namespace App\Domains\QC\Services;

use App\Domains\QC\Models\Inspection;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class InspectionService implements ServiceContract
{
    /** @param array<string,mixed> $params */
    public function paginate(array $params = []): LengthAwarePaginator
    {
        return Inspection::query()
            ->when($params['stage'] ?? null, fn ($q, $v) => $q->where('stage', $v))
            ->when($params['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($params['item_master_id'] ?? null, fn ($q, $v) => $q->where('item_master_id', $v))
            ->with(['itemMaster', 'inspector', 'template'])
            ->orderByDesc('inspection_date')
            ->paginate((int) ($params['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, int $userId): Inspection
    {
        return DB::transaction(function () use ($data, $userId) {
            /** @var Inspection $inspection */
            $inspection = Inspection::create([
                'stage'                  => $data['stage'],
                'inspection_template_id' => $data['inspection_template_id'] ?? null,
                'goods_receipt_id'       => $data['goods_receipt_id'] ?? null,
                'production_order_id'    => $data['production_order_id'] ?? null,
                'item_master_id'         => $data['item_master_id'] ?? null,
                'lot_batch_id'           => $data['lot_batch_id'] ?? null,
                'qty_inspected'          => $data['qty_inspected'],
                'qty_passed'             => 0,
                'qty_failed'             => 0,
                'inspection_date'        => $data['inspection_date'],
                'inspector_id'           => $data['inspector_id'] ?? null,
                'remarks'                => $data['remarks'] ?? null,
                'created_by_id'          => $userId,
            ]);

            return $inspection;
        });
    }

    /** @param array<int, array<string,mixed>> $results */
    public function recordResults(Inspection $inspection, array $results, int $passed, int $failed): Inspection
    {
        if (!in_array($inspection->status, ['open'], true)) {
            throw new DomainException('QC_INSPECTION_NOT_OPEN');
        }

        return DB::transaction(function () use ($inspection, $results, $passed, $failed) {
            $inspection->results()->delete();

            foreach ($results as $row) {
                $inspection->results()->create([
                    'inspection_template_item_id' => $row['inspection_template_item_id'] ?? null,
                    'criterion'     => $row['criterion'],
                    'actual_value'  => $row['actual_value'] ?? null,
                    'is_conforming' => $row['is_conforming'] ?? null,
                    'remarks'       => $row['remarks'] ?? null,
                ]);
            }

            $allConforming = collect($results)->every(fn ($r) => ($r['is_conforming'] ?? null) !== false);

            $inspection->update([
                'qty_passed' => $passed,
                'qty_failed' => $failed,
                'status'     => $allConforming ? 'passed' : 'failed',
            ]);

            return $inspection->load('results');
        });
    }
}
