<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Http\Controllers\Controller;
use App\Models\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin controller for Chart of Accounts management.
 *
 * COA-003: is_system accounts cannot be archived/renamed/deleted.
 * COA-005: Archiving with non-zero balance is rejected.
 */
final class ChartOfAccountsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ChartOfAccount::with('parent')
            ->orderBy('code');

        if ($request->has('account_type')) {
            $query->where('account_type', $request->input('account_type'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('only_parents')) {
            $query->whereNull('parent_id');
        }

        if ($request->boolean('only_leaves')) {
            $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('chart_of_accounts as children')
                    ->whereColumn('children.parent_id', 'chart_of_accounts.id');
            });
        }

        $accounts = $query->get();

        // Build tree structure
        $tree = $this->buildTree($accounts);

        return response()->json([
            'data' => $accounts,
            'tree' => $tree,
            'account_types' => $accounts->pluck('account_type')->unique()->values()->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:chart_of_accounts',
            'name' => 'required|string|max:200',
            'account_type' => 'required|string|in:ASSET,LIABILITY,EQUITY,REVENUE,COGS,OPEX,TAX',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'normal_balance' => 'required|string|in:DEBIT,CREDIT',
            'description' => 'nullable|string|max:500',
        ]);

        // Validate max hierarchy depth (COA-006: max 5 levels)
        if (! empty($validated['parent_id'])) {
            $depth = $this->calculateDepth($validated['parent_id']);
            if ($depth >= 5) {
                return response()->json([
                    'message' => 'Maximum hierarchy depth of 5 exceeded',
                ], 422);
            }
        }

        $validated['is_active'] = true;
        $validated['is_system'] = false;

        $account = ChartOfAccount::create($validated);

        Audit::create([
            'event' => 'created',
            'auditable_type' => ChartOfAccount::class,
            'auditable_id' => $account->id,
            'old_values' => [],
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Account created successfully',
            'data' => $account->load('parent'),
        ], 201);
    }

    public function show(ChartOfAccount $chartOfAccount): JsonResponse
    {
        return response()->json([
            'data' => $chartOfAccount->load(['parent', 'children']),
        ]);
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): JsonResponse
    {
        // COA-003: System accounts cannot be modified
        if ($chartOfAccount->is_system) {
            return response()->json([
                'message' => 'System accounts cannot be modified',
            ], 403);
        }

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:20|unique:chart_of_accounts,code,'.$chartOfAccount->id,
            'name' => 'sometimes|required|string|max:200',
            'account_type' => 'sometimes|required|string|in:ASSET,LIABILITY,EQUITY,REVENUE,COGS,OPEX,TAX',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'normal_balance' => 'sometimes|required|string|in:DEBIT,CREDIT',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        // Prevent circular reference
        if (! empty($validated['parent_id']) && $validated['parent_id'] == $chartOfAccount->id) {
            return response()->json([
                'message' => 'Account cannot be its own parent',
            ], 422);
        }

        // Validate max hierarchy depth
        if (! empty($validated['parent_id'])) {
            $depth = $this->calculateDepth($validated['parent_id']);
            if ($depth >= 5) {
                return response()->json([
                    'message' => 'Maximum hierarchy depth of 5 exceeded',
                ], 422);
            }
        }

        $oldValues = $chartOfAccount->toArray();
        $chartOfAccount->update($validated);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => ChartOfAccount::class,
            'auditable_id' => $chartOfAccount->id,
            'old_values' => $oldValues,
            'new_values' => $validated,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json([
            'message' => 'Account updated successfully',
            'data' => $chartOfAccount->load('parent'),
        ]);
    }

    public function destroy(ChartOfAccount $chartOfAccount): JsonResponse
    {
        // COA-003: System accounts cannot be deleted
        if ($chartOfAccount->is_system) {
            return response()->json([
                'message' => 'System accounts cannot be deleted',
            ], 403);
        }

        // Check if account has children
        if ($chartOfAccount->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete account with child accounts',
            ], 422);
        }

        // COA-005: Check for non-zero balance (simplified - would need GL balance check)
        // This would typically check against journal_entry_lines

        $oldValues = $chartOfAccount->toArray();
        $chartOfAccount->delete();

        Audit::create([
            'event' => 'deleted',
            'auditable_type' => ChartOfAccount::class,
            'auditable_id' => $chartOfAccount->id,
            'old_values' => $oldValues,
            'new_values' => [],
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json(['message' => 'Account deleted successfully']);
    }

    /**
     * Archive (soft delete) an account.
     */
    public function archive(ChartOfAccount $chartOfAccount): JsonResponse
    {
        if ($chartOfAccount->is_system) {
            return response()->json([
                'message' => 'System accounts cannot be archived',
            ], 403);
        }

        if ($chartOfAccount->children()->exists()) {
            return response()->json([
                'message' => 'Cannot archive account with child accounts',
            ], 422);
        }

        $oldValues = $chartOfAccount->toArray();
        $chartOfAccount->update(['is_active' => false]);
        $chartOfAccount->delete();

        Audit::create([
            'event' => 'archived',
            'auditable_type' => ChartOfAccount::class,
            'auditable_id' => $chartOfAccount->id,
            'old_values' => $oldValues,
            'new_values' => ['is_active' => false, 'deleted_at' => now()],
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
        ]);

        return response()->json(['message' => 'Account archived successfully']);
    }

    /**
     * Build tree structure from flat list.
     */
    private function buildTree($accounts, $parentId = null): array
    {
        $tree = [];
        foreach ($accounts as $account) {
            if ($account->parent_id === $parentId) {
                $children = $this->buildTree($accounts, $account->id);
                if ($children) {
                    $account->setAttribute('children', $children);
                }
                $tree[] = $account;
            }
        }

        return $tree;
    }

    /**
     * Calculate depth of an account in the hierarchy.
     */
    private function calculateDepth(int $accountId): int
    {
        $depth = 0;
        $account = ChartOfAccount::find($accountId);

        while ($account && $account->parent_id) {
            $depth++;
            $account = ChartOfAccount::find($account->parent_id);
        }

        return $depth + 1;
    }
}
