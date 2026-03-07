<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Payroll\Models\TrainTaxBracket;
use App\Http\Controllers\Controller;
use App\Models\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin controller for TRAIN Tax Brackets management.
 *
 * Tax brackets are effective-date versioned. Creating a new bracket
 * with a later effective date automatically supersedes older brackets.
 */
final class TaxBracketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TrainTaxBracket::query()
            ->orderBy('effective_date', 'desc')
            ->orderBy('income_from');

        if ($request->boolean('with_archived')) {
            $query->withTrashed();
        }

        if ($request->has('effective_date')) {
            $query->forDate($request->input('effective_date'));
        }

        $brackets = $query->get();

        // Group by effective date for better display
        $grouped = $brackets->groupBy('effective_date');

        return response()->json([
            'data' => $brackets,
            'grouped' => $grouped,
            'versions' => $grouped->keys()->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'effective_date' => 'required|date',
            'income_from' => 'required|numeric|min:0',
            'income_to' => 'nullable|numeric|gte:income_from',
            'base_tax' => 'required|numeric|min:0',
            'excess_rate' => 'required|numeric|between:0,1',
            'notes' => 'nullable|string|max:500',
        ]);

        $bracket = TrainTaxBracket::create($validated);

        Audit::create([
            'event' => 'created',
            'auditable_type' => TrainTaxBracket::class,
            'auditable_id' => $bracket->id,
            'old_values' => [],
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Tax bracket created successfully',
            'data' => $bracket,
        ], 201);
    }

    public function show(TrainTaxBracket $bracket): JsonResponse
    {
        return response()->json(['data' => $bracket]);
    }

    public function update(Request $request, TrainTaxBracket $bracket): JsonResponse
    {
        $validated = $request->validate([
            'effective_date' => 'sometimes|required|date',
            'income_from' => 'sometimes|required|numeric|min:0',
            'income_to' => 'nullable|numeric|gte:income_from',
            'base_tax' => 'sometimes|required|numeric|min:0',
            'excess_rate' => 'sometimes|required|numeric|between:0,1',
            'notes' => 'nullable|string|max:500',
        ]);

        $oldValues = $bracket->toArray();
        $bracket->update($validated);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => TrainTaxBracket::class,
            'auditable_id' => $bracket->id,
            'old_values' => $oldValues,
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Tax bracket updated successfully',
            'data' => $bracket,
        ]);
    }

    public function destroy(TrainTaxBracket $bracket): JsonResponse
    {
        $oldValues = $bracket->toArray();
        $bracket->delete();

        Audit::create([
            'event' => 'deleted',
            'auditable_type' => TrainTaxBracket::class,
            'auditable_id' => $bracket->id,
            'old_values' => $oldValues,
            'new_values' => [],
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json(['message' => 'Tax bracket deleted successfully']);
    }

    /**
     * Get the currently active tax brackets for payroll computation.
     */
    public function active(): JsonResponse
    {
        $date = now()->toDateString();
        $brackets = TrainTaxBracket::forDate($date)
            ->orderBy('income_from')
            ->get();

        return response()->json([
            'effective_as_of' => $brackets->first()?->effective_date?->toDateString(),
            'data' => $brackets,
        ]);
    }
}
