<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounting\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps GeneralLedgerService::generate() output for the API response.
 */
final class GeneralLedgerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $report */
        $report = $this->resource;

        return [
            'data' => [
                'account' => $report['account'],
                'opening_balance' => $report['opening_balance'],
                'lines' => $report['lines'],
                'closing_balance' => $report['closing_balance'],
            ],
            'meta' => [
                'filters' => $report['filters'],
                'generated_at' => $report['generated_at'],
            ],
        ];
    }
}
