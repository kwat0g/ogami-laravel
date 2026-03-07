<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Payroll\Models\PagibigContributionTable;
use App\Http\Controllers\Controller;
use App\Models\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin controller for Pag-IBIG Contribution Table management.
 *
 * Pag-IBIG contributions are effective-date versioned.
 */
final class PagibigContributionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PagibigContributionTable::query()
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
            'salary_threshold' => 'required|numeric|min:0',
            'employee_rate_below' => 'required|numeric|between:0,1',
            'employee_rate_above' => 'required|numeric|between:0,1',
            'employee_cap_monthly' => 'required|numeric|min:0',
            'employer_rate' => 'required|numeric|between:0,1',
            'legal_basis' => 'nullable|string|max:500',
        ]);

        $row = PagibigContributionTable::create($validated);

        Audit::create([
            'event' => 'created',
            'auditable_type' => PagibigContributionTable::class,
            'auditable_id' => $row->id,
            'old_values' => [],
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Pag-IBIG contribution rate created successfully',
            'data' => $row,
        ], 201);
    }

    public function show(PagibigContributionTable $pagibig): JsonResponse
    {
        return response()->json(['data' => $pagibig]);
    }

    public function update(Request $request, PagibigContributionTable $pagibig): JsonResponse
    {
        $validated = $request->validate([
            'effective_date' => 'sometimes|required|date',
            'salary_threshold' => 'sometimes|required|numeric|min:0',
            'employee_rate_below' => 'sometimes|required|numeric|between:0,1',
            'employee_rate_above' => 'sometimes|required|numeric|between:0,1',
            'employee_cap_monthly' => 'sometimes|required|numeric|min:0',
            'employer_rate' => 'sometimes|required|numeric|between:0,1',
            'legal_basis' => 'nullable|string|max:500',
        ]);

        $oldValues = $pagibig->toArray();
        $pagibig->update($validated);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => PagibigContributionTable::class,
            'auditable_id' => $pagibig->id,
            'old_values' => $oldValues,
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Pag-IBIG contribution rate updated successfully',
            'data' => $pagibig,
        ]);
    }

    public function destroy(PagibigContributionTable $pagibig): JsonResponse
    {
        $oldValues = $pagibig->toArray();
        $pagibig->delete();

        Audit::create([
            'event' => 'deleted',
            'auditable_type' => PagibigContributionTable::class,
            'auditable_id' => $pagibig->id,
            'old_values' => $oldValues,
            'new_values' => [],
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json(['message' => 'Pag-IBIG contribution rate deleted successfully']);
    }

    /**
     * Get the currently active Pag-IBIG contribution rate.
     */
    public function active(): JsonResponse
    {
        $date = now()->toDateString();
        $rate = PagibigContributionTable::forDate($date)->first();

        return response()->json([
            'effective_as_of' => $rate?->effective_date?->toDateString(),
            'data' => $rate,
        ]);
    }
}
