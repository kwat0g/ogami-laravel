<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Payroll\Models\MinimumWageRate;
use App\Http\Controllers\Controller;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin controller for Minimum Wage Rates management.
 *
 * Minimum wage rates are effective-date versioned per region.
 */
final class MinimumWageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MinimumWageRate::query()
            ->orderBy('region')
            ->orderBy('effective_date', 'desc');

        if ($request->boolean('with_archived')) {
            $query->withTrashed();
        }

        if ($request->has('region')) {
            $query->where('region', $request->input('region'));
        }

        if ($request->has('effective_date')) {
            $query->forDate($request->input('effective_date'));
        }

        $rates = $query->get();

        // Group by region
        $byRegion = $rates->groupBy('region');

        return response()->json([
            'data' => $rates,
            'by_region' => $byRegion,
            'regions' => $rates->pluck('region')->unique()->values()->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'effective_date' => 'required|date',
            'region' => 'required|string|max:20',
            'daily_rate' => 'required|numeric|min:0',
            'wage_order_reference' => 'nullable|string|max:100',
        ]);

        $rate = MinimumWageRate::create($validated);

        Audit::create([
            'event' => 'created',
            'auditable_type' => MinimumWageRate::class,
            'auditable_id' => $rate->id,
            'old_values' => [],
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Minimum wage rate created successfully',
            'data' => $rate,
        ], 201);
    }

    public function show(MinimumWageRate $minimumWage): JsonResponse
    {
        return response()->json(['data' => $minimumWage]);
    }

    public function update(Request $request, MinimumWageRate $minimumWage): JsonResponse
    {
        $validated = $request->validate([
            'effective_date' => 'sometimes|required|date',
            'region' => 'sometimes|required|string|max:20',
            'daily_rate' => 'sometimes|required|numeric|min:0',
            'wage_order_reference' => 'nullable|string|max:100',
        ]);

        $oldValues = $minimumWage->toArray();
        $minimumWage->update($validated);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => MinimumWageRate::class,
            'auditable_id' => $minimumWage->id,
            'old_values' => $oldValues,
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Minimum wage rate updated successfully',
            'data' => $minimumWage,
        ]);
    }

    public function destroy(MinimumWageRate $minimumWage): JsonResponse
    {
        $oldValues = $minimumWage->toArray();
        $minimumWage->delete();

        Audit::create([
            'event' => 'deleted',
            'auditable_type' => MinimumWageRate::class,
            'auditable_id' => $minimumWage->id,
            'old_values' => $oldValues,
            'new_values' => [],
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json(['message' => 'Minimum wage rate deleted successfully']);
    }

    /**
     * Get all regions with their current minimum wage.
     */
    public function currentByRegion(): JsonResponse
    {
        $date = now()->toDateString();
        $regions = MinimumWageRate::select('region')
            ->distinct()
            ->pluck('region');

        $current = [];
        foreach ($regions as $region) {
            $rate = MinimumWageRate::forRegion($region)
                ->forDate($date)
                ->first();
            if ($rate) {
                $current[$region] = $rate;
            }
        }

        return response()->json([
            'effective_as_of' => $date,
            'data' => $current,
        ]);
    }
}
