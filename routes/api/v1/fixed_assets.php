<?php

declare(strict_types=1);

use App\Http\Controllers\FixedAssets\FixedAssetController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
|--------------------------------------------------------------------------
| Fixed Assets Routes — /api/v1/fixed-assets/*
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'module_access:fixed_assets'])->group(function (): void {
    // ── Asset Categories ─────────────────────────────────────────────────
    Route::get('categories', [FixedAssetController::class, 'indexCategories'])
        ->name('categories.index');
    Route::post('categories', [FixedAssetController::class, 'storeCategory'])
        ->middleware('throttle:api-action')
        ->name('categories.store');

    // ── Asset Register ───────────────────────────────────────────────────
    Route::get('', [FixedAssetController::class, 'index'])
        ->name('index');
    Route::post('', [FixedAssetController::class, 'store'])
        ->middleware('throttle:api-action')
        ->name('store');

    // ── Depreciation (batch by fiscal period) ───────────────────────────
    Route::post('depreciate', [FixedAssetController::class, 'depreciatePeriod'])
        ->middleware('throttle:api-action')
        ->name('depreciate');

    // ── Disposal ─────────────────────────────────────────────────────────
    Route::post('{fixedAsset}/dispose', [FixedAssetController::class, 'dispose'])
        ->middleware('throttle:api-action')
        ->name('dispose');

    // ── Depreciation Schedule Export (CSV) ───────────────────────────────────
    Route::get('depreciation-export', function (): StreamedResponse {
        abort_unless(auth()->user()?->hasPermissionTo('fixed_assets.view'), 403, 'Unauthorized');
        $rows = DB::table('asset_depreciation_entries')
            ->join('fixed_assets', 'asset_depreciation_entries.fixed_asset_id', '=', 'fixed_assets.id')
            ->select(
                'fixed_assets.asset_code',
                'fixed_assets.name as asset_name',
                'asset_depreciation_entries.fiscal_period',
                'asset_depreciation_entries.depreciation_amount',
                'asset_depreciation_entries.accumulated_depreciation',
                'asset_depreciation_entries.book_value',
                'asset_depreciation_entries.created_at',
            )
            ->orderBy('fixed_assets.asset_code')
            ->orderBy('asset_depreciation_entries.fiscal_period')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Asset Code', 'Asset Name', 'Period', 'Depreciation', 'Accumulated', 'Book Value', 'Processed']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->asset_code, $r->asset_name, $r->fiscal_period,
                    number_format((float) $r->depreciation_amount, 2),
                    number_format((float) $r->accumulated_depreciation, 2),
                    number_format((float) $r->book_value, 2),
                    $r->created_at,
                ]);
            }
            fclose($out);
        }, 'depreciation_schedule_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    })->name('depreciation-export');

    // ── Asset Transfers (Phase 4) ─────────────────────────────────────────
    Route::prefix('transfers')->name('transfers.')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
            $transfers = \App\Domains\FixedAssets\Models\AssetTransfer::with([
                    'fixedAsset', 'fromDepartment', 'toDepartment', 'requestedBy', 'approvedBy',
                ])
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->orderByDesc('id')
                ->paginate((int) ($request->input('per_page', 20)));
            return response()->json($transfers);
        })->name('index');

        Route::post('/', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
            $data = $request->validate([
                'fixed_asset_id' => ['required', 'integer', 'exists:fixed_assets,id'],
                'from_department_id' => ['required', 'integer', 'exists:departments,id'],
                'to_department_id' => ['required', 'integer', 'exists:departments,id', 'different:from_department_id'],
                'transfer_date' => ['required', 'date'],
                'reason' => ['sometimes', 'string'],
            ]);
            $transfer = \App\Domains\FixedAssets\Models\AssetTransfer::create([
                ...$data,
                'status' => 'pending',
                'requested_by_id' => $request->user()->id,
            ]);
            return response()->json(['data' => $transfer], 201);
        })->name('store');

        Route::patch('/{assetTransfer:ulid}/approve', function (\Illuminate\Http\Request $request, \App\Domains\FixedAssets\Models\AssetTransfer $assetTransfer): \Illuminate\Http\JsonResponse {
            // SoD: requester cannot approve their own transfer
            if ($request->user()->id === $assetTransfer->requested_by_id) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'SOD_SELF_APPROVAL',
                    'message' => 'You cannot approve a transfer you requested (Separation of Duties).',
                ], 403);
            }

            $assetTransfer->update([
                'status' => 'approved',
                'approved_by_id' => $request->user()->id,
                'approved_at' => now(),
            ]);
            return response()->json(['data' => $assetTransfer->fresh(['fixedAsset', 'fromDepartment', 'toDepartment', 'requestedBy', 'approvedBy'])]);
        })->name('approve')->middleware('throttle:api-action');
    });

    // ── Parameterized asset routes (MUST be last to avoid catching
    //    literal paths like "transfers", "depreciate", etc.) ──────────────
    Route::get('{fixedAsset}', [FixedAssetController::class, 'show'])
        ->name('show');
    Route::put('{fixedAsset}', [FixedAssetController::class, 'update'])
        ->middleware('throttle:api-action')
        ->name('update');
});
