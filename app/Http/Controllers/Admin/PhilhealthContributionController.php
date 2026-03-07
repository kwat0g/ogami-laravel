<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Payroll\Models\PhilhealthPremiumTable;
use App\Http\Controllers\Controller;
use App\Models\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin controller for PhilHealth Premium Table management.
 *
 * PhilHealth premiums are effective-date versioned.
 */
final class PhilhealthContributionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PhilhealthPremiumTable::query()
            ->orderBy('effective_date', 'desc');

        if ($request->boolean('with_archived')) {
            $query->withTrashed();
        }

        if ($request->has('effective_date')) {
            $query->forDate($request->input('effective_date'));
        }

        $rows = $query->get();

        return response()->json([
            'data' => $rows,
            'versions' => $rows->pluck('effective_date')->unique()->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'effective_date' => 'required|date',
            'salary_floor' => 'nullable|numeric|min:0',
            'salary_ceiling' => 'nullable|numeric',
            'premium_rate' => 'required|numeric|between:0,1',
            'min_monthly_premium' => 'required|numeric|min:0',
            'max_monthly_premium' => 'required|numeric|gte:min_monthly_premium',
            'legal_basis' => 'nullable|string|max:500',
        ]);

        $row = PhilhealthPremiumTable::create($validated);

        Audit::create([
            'event' => 'created',
            'auditable_type' => PhilhealthPremiumTable::class,
            'auditable_id' => $row->id,
            'old_values' => [],
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'PhilHealth premium rate created successfully',
            'data' => $row,
        ], 201);
    }

    public function show(PhilhealthPremiumTable $philhealth): JsonResponse
    {
        return response()->json(['data' => $philhealth]);
    }

    public function update(Request $request, PhilhealthPremiumTable $philhealth): JsonResponse
    {
        $validated = $request->validate([
            'effective_date' => 'sometimes|required|date',
            'salary_floor' => 'nullable|numeric|min:0',
            'salary_ceiling' => 'nullable|numeric',
            'premium_rate' => 'sometimes|required|numeric|between:0,1',
            'min_monthly_premium' => 'sometimes|required|numeric|min:0',
            'max_monthly_premium' => 'sometimes|required|numeric|gte:min_monthly_premium',
            'legal_basis' => 'nullable|string|max:500',
        ]);

        $oldValues = $philhealth->toArray();
        $philhealth->update($validated);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => PhilhealthPremiumTable::class,
            'auditable_id' => $philhealth->id,
            'old_values' => $oldValues,
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'PhilHealth premium rate updated successfully',
            'data' => $philhealth,
        ]);
    }

    public function destroy(PhilhealthPremiumTable $philhealth): JsonResponse
    {
        $oldValues = $philhealth->toArray();
        $philhealth->delete();

        Audit::create([
            'event' => 'deleted',
            'auditable_type' => PhilhealthPremiumTable::class,
            'auditable_id' => $philhealth->id,
            'old_values' => $oldValues,
            'new_values' => [],
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json(['message' => 'PhilHealth premium rate deleted successfully']);
    }

    /**
     * Get the currently active PhilHealth premium rate.
     */
    public function active(): JsonResponse
    {
        $date = now()->toDateString();
        $rate = PhilhealthPremiumTable::forDate($date)->first();

        return response()->json([
            'effective_as_of' => $rate?->effective_date?->toDateString(),
            'data' => $rate,
        ]);
    }
}
