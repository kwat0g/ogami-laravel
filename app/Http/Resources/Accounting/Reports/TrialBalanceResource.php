<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounting\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps TrialBalanceService::generate() output for the API response.
 */
final class TrialBalanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $report */
        $report = $this->resource;

        return [
            'data' => [
                'accounts' => $report['accounts'],
                'totals' => $report['totals'],
            ],
            'meta' => [
                'filters' => $report['filters'],
                'generated_at' => $report['generated_at'],
            ],
        ];
    }
}
