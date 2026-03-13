<?php

declare(strict_types=1);

use App\Http\Controllers\FixedAssets\FixedAssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fixed Assets Routes — /api/v1/fixed-assets/*
| All routes require Sanctum authentication.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function (): void {
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
    Route::get('{fixedAsset}', [FixedAssetController::class, 'show'])
        ->name('show');
    Route::put('{fixedAsset}', [FixedAssetController::class, 'update'])
        ->middleware('throttle:api-action')
        ->name('update');

    // ── Depreciation (batch by fiscal period) ───────────────────────────
    Route::post('depreciate', [FixedAssetController::class, 'depreciatePeriod'])
        ->middleware('throttle:api-action')
        ->name('depreciate');

    // ── Disposal ─────────────────────────────────────────────────────────
    Route::post('{fixedAsset}/dispose', [FixedAssetController::class, 'dispose'])
        ->middleware('throttle:api-action')
        ->name('dispose');

    // ── Depreciation Schedule Export (CSV) ───────────────────────────────────
    Route::get('depreciation-export', function (): \Symfony\Component\HttpFoundation\StreamedResponse {        abort_unless(auth()->user()?->hasPermissionTo('fixed_assets.view'), 403, 'Unauthorized');
        $rows = \Illuminate\Support\Facades\DB::table('asset_depreciation_entries')
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
            if ($out === false) return;
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
        }, 'depreciation_schedule_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    })->name('depreciation-export');
});
