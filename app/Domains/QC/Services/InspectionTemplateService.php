<?php

declare(strict_types=1);

namespace App\Domains\QC\Services;

use App\Domains\QC\Models\InspectionTemplate;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class InspectionTemplateService implements ServiceContract
{
    /** @param array<string,mixed> $params */
    public function paginate(array $params = []): LengthAwarePaginator
    {
        return InspectionTemplate::query()
            ->when($params['with_archived'] ?? false, fn ($q) => $q->withTrashed())
            ->when($params['stage'] ?? null, fn ($q, $v) => $q->where('stage', $v))
            ->when(isset($params['is_active']), fn ($q) => $q->where('is_active', filter_var($params['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->with('items')
            ->orderBy('name')
            ->paginate((int) ($params['per_page'] ?? 20));
    }

    /** @param array<string,mixed> $data */
    public function store(array $data, int $userId): InspectionTemplate
    {
        return DB::transaction(function () use ($data, $userId) {
            /** @var InspectionTemplate $template */
            $template = InspectionTemplate::create([
                'name'         => $data['name'],
                'stage'        => $data['stage'],
                'description'  => $data['description'] ?? null,
                'is_active'    => $data['is_active'] ?? true,
                'created_by_id' => $userId,
            ]);

            foreach ($data['items'] ?? [] as $idx => $item) {
                $template->items()->create([
                    'criterion'       => $item['criterion'],
                    'method'          => $item['method'] ?? null,
                    'acceptable_range' => $item['acceptable_range'] ?? null,
                    'sort_order'      => (int) ($item['sort_order'] ?? $idx),
                ]);
            }

            return $template->load('items');
        });
    }

    /** @param array<string,mixed> $data */
    public function update(InspectionTemplate $template, array $data): InspectionTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            $template->update([
                'name'        => $data['name'] ?? $template->name,
                'stage'       => $data['stage'] ?? $template->stage,
                'description' => $data['description'] ?? $template->description,
                'is_active'   => $data['is_active'] ?? $template->is_active,
            ]);

            if (array_key_exists('items', $data)) {
                $template->items()->delete();
                foreach ($data['items'] as $idx => $item) {
                    $template->items()->create([
                        'criterion'        => $item['criterion'],
                        'method'           => $item['method'] ?? null,
                        'acceptable_range' => $item['acceptable_range'] ?? null,
                        'sort_order'       => (int) ($item['sort_order'] ?? $idx),
                    ]);
                }
            }

            return $template->load('items');
        });
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, InspectionTemplate> */
    public function allForStage(string $stage)
    {
        return InspectionTemplate::where('stage', $stage)->where('is_active', true)->with('items')->orderBy('name')->get();
    }
}
