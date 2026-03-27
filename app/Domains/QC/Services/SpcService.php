<?php

declare(strict_types=1);

namespace App\Domains\QC\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistical Process Control (SPC) Service.
 *
 * Provides control chart data, process capability indices (Cpk/Ppk),
 * and out-of-control detection for inspection measurements.
 */
final class SpcService implements ServiceContract
{
    /**
     * Generate control chart data for a specific inspection parameter.
     *
     * @return array{parameter: string, mean: float, ucl: float, lcl: float, usl: float|null, lsl: float|null, data_points: array, out_of_control_count: int}
     */
    public function controlChart(
        int $inspectionTemplateId,
        string $parameterName,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?float $usl = null,
        ?float $lsl = null,
    ): array {
        // Fetch measurement data from inspection results
        $measurements = DB::table('inspections')
            ->where('template_id', $inspectionTemplateId)
            ->where('status', 'completed')
            ->when($fromDate, fn ($q, $v) => $q->where('inspections.created_at', '>=', $v))
            ->when($toDate, fn ($q, $v) => $q->where('inspections.created_at', '<=', $v . ' 23:59:59'))
            ->whereNull('inspections.deleted_at')
            ->orderBy('inspections.created_at')
            ->select('inspections.id', 'inspections.created_at', 'inspections.results')
            ->get();

        // Extract numeric values for the parameter from JSON results
        $dataPoints = [];
        foreach ($measurements as $m) {
            $results = json_decode($m->results ?? '{}', true);
            if (isset($results[$parameterName]) && is_numeric($results[$parameterName])) {
                $dataPoints[] = [
                    'inspection_id' => $m->id,
                    'date' => $m->created_at,
                    'value' => (float) $results[$parameterName],
                ];
            }
        }

        if (empty($dataPoints)) {
            return [
                'parameter' => $parameterName,
                'mean' => 0,
                'ucl' => 0,
                'lcl' => 0,
                'usl' => $usl,
                'lsl' => $lsl,
                'data_points' => [],
                'out_of_control_count' => 0,
                'cpk' => null,
                'ppk' => null,
            ];
        }

        $values = array_column($dataPoints, 'value');
        $mean = array_sum($values) / count($values);
        $stdDev = $this->standardDeviation($values);

        // Control limits: mean +/- 3 sigma
        $ucl = $mean + (3 * $stdDev);
        $lcl = $mean - (3 * $stdDev);

        // Mark out-of-control points
        $outOfControl = 0;
        foreach ($dataPoints as &$point) {
            $point['out_of_control'] = $point['value'] > $ucl || $point['value'] < $lcl;
            if ($point['out_of_control']) {
                $outOfControl++;
            }
        }

        // Process capability indices
        $cpk = null;
        $ppk = null;
        if ($usl !== null && $lsl !== null && $stdDev > 0) {
            $cpu = ($usl - $mean) / (3 * $stdDev);
            $cpl = ($mean - $lsl) / (3 * $stdDev);
            $cpk = round(min($cpu, $cpl), 4);
            $ppk = $cpk; // Ppk uses overall std dev (same in this calculation)
        }

        return [
            'parameter' => $parameterName,
            'mean' => round($mean, 4),
            'std_dev' => round($stdDev, 4),
            'ucl' => round($ucl, 4),
            'lcl' => round($lcl, 4),
            'usl' => $usl,
            'lsl' => $lsl,
            'data_points' => $dataPoints,
            'sample_count' => count($dataPoints),
            'out_of_control_count' => $outOfControl,
            'cpk' => $cpk,
            'ppk' => $ppk,
        ];
    }

    /**
     * Calculate standard deviation.
     *
     * @param float[] $values
     */
    private function standardDeviation(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $sumSquaredDiffs = 0.0;
        foreach ($values as $value) {
            $sumSquaredDiffs += ($value - $mean) ** 2;
        }

        return sqrt($sumSquaredDiffs / ($count - 1));
    }
}
