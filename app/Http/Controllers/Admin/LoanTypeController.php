<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Loan\Models\LoanType;
use App\Http\Controllers\Controller;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin controller for Loan Type management.
 */
final class LoanTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LoanType::query()
            ->orderBy('category')
            ->orderBy('name');

        if ($request->boolean('with_archived')) {
            $query->withTrashed();
        }

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $types = $query->get();

        return response()->json([
            'data' => $types,
            'by_category' => $types->groupBy('category'),
            'categories' => $types->pluck('category')->unique()->values()->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:loan_types',
            'name' => 'required|string|max:100',
            'category' => 'required|string|in:government,company',
            'description' => 'nullable|string|max:500',
            'interest_rate_annual' => 'required|numeric|between:0,1',
            'max_term_months' => 'required|integer|min:1',
            'max_amount_centavos' => 'nullable|integer|min:0',
            'min_amount_centavos' => 'required|integer|min:0',
            'subject_to_min_wage_protection' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['subject_to_min_wage_protection'] = $validated['subject_to_min_wage_protection'] ?? true;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $type = LoanType::create($validated);

        Audit::create([
            'event' => 'created',
            'auditable_type' => LoanType::class,
            'auditable_id' => $type->id,
            'old_values' => [],
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Loan type created successfully',
            'data' => $type,
        ], 201);
    }

    public function show(LoanType $loanType): JsonResponse
    {
        return response()->json(['data' => $loanType]);
    }

    public function update(Request $request, LoanType $loanType): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:20|unique:loan_types,code,'.$loanType->id,
            'name' => 'sometimes|required|string|max:100',
            'category' => 'sometimes|required|string|in:government,company',
            'description' => 'nullable|string|max:500',
            'interest_rate_annual' => 'sometimes|required|numeric|between:0,1',
            'max_term_months' => 'sometimes|required|integer|min:1',
            'max_amount_centavos' => 'nullable|integer|min:0',
            'min_amount_centavos' => 'sometimes|required|integer|min:0',
            'subject_to_min_wage_protection' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $oldValues = $loanType->toArray();
        $loanType->update($validated);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => LoanType::class,
            'auditable_id' => $loanType->id,
            'old_values' => $oldValues,
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Loan type updated successfully',
            'data' => $loanType,
        ]);
    }

    public function destroy(LoanType $loanType): JsonResponse
    {
        $oldValues = $loanType->toArray();
        $loanType->delete();

        Audit::create([
            'event' => 'deleted',
            'auditable_type' => LoanType::class,
            'auditable_id' => $loanType->id,
            'old_values' => $oldValues,
            'new_values' => [],
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json(['message' => 'Loan type deleted successfully']);
    }

    /**
     * Get loan types by category.
     */
    public function byCategory(string $category): JsonResponse
    {
        $types = LoanType::where('category', $category)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'category' => $category,
            'data' => $types,
        ]);
    }
}
