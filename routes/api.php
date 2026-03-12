<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — Main Router
|--------------------------------------------------------------------------
| This file is the single entry point for all v1 API routes.
| Domain route groups are required below so each domain owns its routes.
|--------------------------------------------------------------------------
*/

// ── Health Check (throttled, no auth) ──────────────────────────────────────────
Route::get('health', function () {
    $dbOk = rescue(fn () => (bool) \Illuminate\Support\Facades\DB::connection()->getPdo(), false);
    $redisOk = rescue(fn () => \Illuminate\Support\Facades\Cache::put('__hc', 'alive', 2) && \Illuminate\Support\Facades\Cache::get('__hc') === 'alive', false);
    $healthy = $dbOk && $redisOk;

    // In production, expose only the aggregate status to avoid infrastructure recon.
    // Internal service breakdown is only included in non-production environments.
    $body = ['status' => $healthy ? 'ok' : 'degraded', 'timestamp' => now()->toIso8601String()];
    if (! app()->isProduction()) {
        $body['services'] = [
            'database' => $dbOk ? 'ok' : 'error',
            'cache' => $redisOk ? 'ok' : 'error',
        ];
    }

    return response()->json($body, $healthy ? 200 : 503);
})->middleware('throttle:api-health')->name('health');

// ── System restore status (public, no auth — survives session wipe) ────────────
// Reads a Cache key set by BackupController before/after a DB restore.
// The frontend polls this every 2 s to show a warning overlay and auto-logout.
//
// Response shape:
//   { "in_progress": true,  "completed": false }  — restore actively running
//   { "in_progress": true,  "completed": true  }  — done, 15 s visibility window
//   { "in_progress": false, "completed": false }  — nothing in progress
Route::get('v1/system/restore-status', function () {
    $status = \Illuminate\Support\Facades\Cache::get('system.restore_in_progress', false);
    return response()->json([
        'in_progress' => $status !== false,
        'completed'   => $status === 'done',
    ]);
})->middleware('throttle:60,1')->name('system.restore-status');

Route::prefix('v1')->name('v1.')->group(function () {
    // ── Auth ────────────────────────────────────────────────────────────────
    // Auth routes keep their own brute-force throttle (AuthService.php)
    Route::prefix('auth')->name('auth.')->group(
        base_path('routes/api/v1/auth.php')
    );

    // ── Domain route groups (rate-limited: 120 reads / 60 writes per min) ───
    Route::middleware(['throttle:api'])->group(function () {
        Route::prefix('hr')->name('hr.')->group(base_path('routes/api/v1/hr.php'));
        Route::prefix('leave')->name('leave.')->group(base_path('routes/api/v1/leave.php'));
        Route::prefix('loans')->name('loans.')->group(base_path('routes/api/v1/loans.php'));
        Route::prefix('attendance')->name('attendance.')->group(base_path('routes/api/v1/attendance.php'));
        Route::prefix('payroll')->name('payroll.')->group(base_path('routes/api/v1/payroll.php'));
        Route::prefix('reports')->name('reports.')->group(base_path('routes/api/v1/reports.php'));
        Route::prefix('employee')->name('employee.')->group(base_path('routes/api/v1/employee.php'));
        Route::prefix('accounting')->name('accounting.')->group(base_path('routes/api/v1/accounting.php'));
        Route::prefix('ar')->name('ar.')->group(base_path('routes/api/v1/ar.php'));
        Route::prefix('tax')->name('tax.')->group(base_path('routes/api/v1/tax.php'));
        Route::prefix('admin')->name('admin.')->group(base_path('routes/api/v1/admin.php'));
        Route::prefix('notifications')->name('notifications.')->group(base_path('routes/api/v1/notifications.php'));
        Route::prefix('dashboard')->name('dashboard.')->group(base_path('routes/api/v1/dashboard.php'));
        Route::prefix('procurement')->name('procurement.')->group(base_path('routes/api/v1/procurement.php'));
            Route::prefix('vendor-portal')->name('vendor-portal.')->group(base_path('routes/api/v1/vendor-portal.php'));
        Route::prefix('inventory')->name('inventory.')->group(base_path('routes/api/v1/inventory.php'));
        Route::prefix('production')->name('production.')->group(base_path('routes/api/v1/production.php'));
        Route::prefix('qc')->name('qc.')->group(base_path('routes/api/v1/qc.php'));
        Route::prefix('maintenance')->name('maintenance.')->group(base_path('routes/api/v1/maintenance.php'));
        Route::prefix('mold')->name('mold.')->group(base_path('routes/api/v1/mold.php'));
        Route::prefix('delivery')->name('delivery.')->group(base_path('routes/api/v1/delivery.php'));
        Route::prefix('iso')->name('iso.')->group(base_path('routes/api/v1/iso.php'));
        Route::prefix('crm')->name('crm.')->group(base_path('routes/api/v1/crm.php'));
        Route::prefix('fixed-assets')->name('fixed-assets.')->group(base_path('routes/api/v1/fixed_assets.php'));
        Route::prefix('budget')->name('budget.')->group(base_path('routes/api/v1/budget.php'));
        Route::prefix('search')->name('search.')->group(base_path('routes/api/v1/search.php'));
    });
});
