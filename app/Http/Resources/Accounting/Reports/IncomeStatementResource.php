<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounting\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps IncomeStatementService::generate() output for the API response.
 */
final class IncomeStatementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $report */
        $report = $this->resource;

        return [
            'data' => [
                'revenue' => $report['revenue'],
                'cogs' => $report['cogs'],
                'gross_profit' => $report['gross_profit'],
                'operating_expenses' => $report['operating_expenses'],
                'operating_income' => $report['operating_income'],
                'income_tax' => $report['income_tax'],
                'net_income' => $report['net_income'],
            ],
            'meta' => [
                'filters' => $report['filters'],
                'generated_at' => $report['generated_at'],
            ],
        ];
    }
}
