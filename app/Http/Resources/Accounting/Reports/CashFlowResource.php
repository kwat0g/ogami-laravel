<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounting\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps CashFlowService::generate() output for the API response.
 */
final class CashFlowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $report */
        $report = $this->resource;

        return [
            'data' => [
                'operating' => $report['operating'],
                'investing' => $report['investing'],
                'financing' => $report['financing'],
                'net_change_in_cash' => $report['net_change_in_cash'],
                'opening_cash_balance' => $report['opening_cash_balance'],
                'closing_cash_balance' => $report['closing_cash_balance'],
            ],
            'meta' => [
                'filters' => $report['filters'],
                'generated_at' => $report['generated_at'],
            ],
        ];
    }
}
