<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Payroll\Models\SssContributionTable;
use App\Http\Controllers\Controller;
use App\Models\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin controller for SSS Contribution Table management.
 *
 * SSS contributions are effective-date versioned by salary bracket.
 */
final class SssContributionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SssContributionTable::query()
            ->orderBy('effective_date', 'desc')
            ->orderBy('salary_range_from');

        if ($request->boolean('with_archived')) {
            $query->withTrashed();
        }

        if ($request->has('effective_date')) {
            $query->forDate($request->input('effective_date'));
        }

        $rows = $query->get();

        // Group by effective date
        $grouped = $rows->groupBy('effective_date');

        return response()->json([
            'data' => $rows,
            'grouped' => $grouped,
            'versions' => $grouped->keys()->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'effective_date' => 'required|date',
            'salary_range_from' => 'required|numeric|min:0',
            'salary_range_to' => 'nullable|numeric|gte:salary_range_from',
            'monthly_salary_credit' => 'required|numeric|min:0',
            'employee_contribution' => 'required|numeric|min:0',
            'employer_contribution' => 'required|numeric|min:0',
            'ec_contribution' => 'required|numeric|min:0',
        ]);

        $row = SssContributionTable::create($validated);

        Audit::create([
            'event' => 'created',
            'auditable_type' => SssContributionTable::class,
            'auditable_id' => $row->id,
            'old_values' => [],
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'SSS contribution row created successfully',
            'data' => $row,
        ], 201);
    }

    public function show(SssContributionTable $sssContribution): JsonResponse
    {
        return response()->json(['data' => $sssContribution]);
    }

    public function update(Request $request, SssContributionTable $sssContribution): JsonResponse
    {
        $validated = $request->validate([
            'effective_date' => 'sometimes|required|date',
            'salary_range_from' => 'sometimes|required|numeric|min:0',
            'salary_range_to' => 'nullable|numeric|gte:salary_range_from',
            'monthly_salary_credit' => 'sometimes|required|numeric|min:0',
            'employee_contribution' => 'sometimes|required|numeric|min:0',
            'employer_contribution' => 'sometimes|required|numeric|min:0',
            'ec_contribution' => 'sometimes|required|numeric|min:0',
        ]);

        $oldValues = $sssContribution->toArray();
        $sssContribution->update($validated);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => SssContributionTable::class,
            'auditable_id' => $sssContribution->id,
            'old_values' => $oldValues,
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'SSS contribution row updated successfully',
            'data' => $sssContribution,
        ]);
    }

    public function destroy(SssContributionTable $sssContribution): JsonResponse
    {
        $oldValues = $sssContribution->toArray();
        $sssContribution->delete();

        Audit::create([
            'event' => 'deleted',
            'auditable_type' => SssContributionTable::class,
            'auditable_id' => $sssContribution->id,
            'old_values' => $oldValues,
            'new_values' => [],
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json(['message' => 'SSS contribution row deleted successfully']);
    }

    /**
     * Get the currently active SSS contribution schedule.
     */
    public function active(): JsonResponse
    {
        $date = now()->toDateString();
        $rows = SssContributionTable::forDate($date)
            ->orderBy('salary_range_from')
            ->get();

        return response()->json([
            'effective_as_of' => $rows->first()?->effective_date?->toDateString(),
            'total_brackets' => $rows->count(),
            'data' => $rows,
        ]);
    }
}
