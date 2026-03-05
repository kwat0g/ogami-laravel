<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\HR\Models\SalaryGrade;
use App\Http\Controllers\Controller;
use App\Models\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin controller for Salary Grade management.
 */
final class SalaryGradeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SalaryGrade::query()
            ->orderBy('level')
            ->orderBy('code');

        if ($request->has('employment_type')) {
            $query->where('employment_type', $request->input('employment_type'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $grades = $query->get();

        return response()->json([
            'data' => $grades,
            'by_type' => $grades->groupBy('employment_type'),
            'types' => $grades->pluck('employment_type')->unique()->values()->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:salary_grades',
            'name' => 'required|string|max:100',
            'level' => 'required|integer|between:1,20',
            'min_monthly_rate' => 'required|integer|min:0',
            'max_monthly_rate' => 'required|integer|gte:min_monthly_rate',
            'employment_type' => 'required|string|in:regular,contractual,project_based,casual',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $grade = SalaryGrade::create($validated);

        Audit::create([
            'event' => 'created',
            'auditable_type' => SalaryGrade::class,
            'auditable_id' => $grade->id,
            'old_values' => [],
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Salary grade created successfully',
            'data' => $grade,
        ], 201);
    }

    public function show(SalaryGrade $salaryGrade): JsonResponse
    {
        return response()->json(['data' => $salaryGrade]);
    }

    public function update(Request $request, SalaryGrade $salaryGrade): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:20|unique:salary_grades,code,'.$salaryGrade->id,
            'name' => 'sometimes|required|string|max:100',
            'level' => 'sometimes|required|integer|between:1,20',
            'min_monthly_rate' => 'sometimes|required|integer|min:0',
            'max_monthly_rate' => 'sometimes|required|integer|gte:min_monthly_rate',
            'employment_type' => 'sometimes|required|string|in:regular,contractual,project_based,casual',
            'is_active' => 'boolean',
        ]);

        $oldValues = $salaryGrade->toArray();
        $salaryGrade->update($validated);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => SalaryGrade::class,
            'auditable_id' => $salaryGrade->id,
            'old_values' => $oldValues,
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Salary grade updated successfully',
            'data' => $salaryGrade,
        ]);
    }

    public function destroy(SalaryGrade $salaryGrade): JsonResponse
    {
        $oldValues = $salaryGrade->toArray();
        $salaryGrade->delete();

        Audit::create([
            'event' => 'deleted',
            'auditable_type' => SalaryGrade::class,
            'auditable_id' => $salaryGrade->id,
            'old_values' => $oldValues,
            'new_values' => [],
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json(['message' => 'Salary grade deleted successfully']);
    }

    /**
     * Get grades by employment type.
     */
    public function byType(string $type): JsonResponse
    {
        $grades = SalaryGrade::where('employment_type', $type)
            ->where('is_active', true)
            ->orderBy('level')
            ->get();

        return response()->json([
            'employment_type' => $type,
            'data' => $grades,
        ]);
    }
}
