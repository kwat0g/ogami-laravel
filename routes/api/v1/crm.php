<?php

declare(strict_types=1);

use App\Domains\CRM\Models\ClientOrder;
use App\Http\Controllers\CRM\ClientOrderController;
// TODO: Phase 2 — Lead and Opportunity domain models/services not yet implemented
// use App\Http\Controllers\CRM\LeadController;
// use App\Http\Controllers\CRM\OpportunityController;
use App\Http\Controllers\CRM\TicketController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CRM Module Routes — /api/v1/crm/
|--------------------------------------------------------------------------
| Accessible by: staff with crm.tickets.* permissions AND client role users.
| Ticket scoping for clients is enforced in TicketPolicy and TicketService.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'module_access:crm'])->group(function () {

    // ── Leads ────────────────────────────────────────────────────────────
    // TODO: Phase 2 — Lead model, LeadService, and LeadScoringService not yet implemented.
    // Uncomment when app/Domains/CRM/Models/Lead.php and related services are created.
    // Route::prefix('leads')->name('leads.')->group(function () {
    //     Route::get('/', [LeadController::class, 'index'])->name('index');
    //     Route::post('/', [LeadController::class, 'store'])->name('store');
    //     Route::get('/scores', ...)->name('scores');
    //     Route::post('/auto-qualify', ...)->name('auto-qualify');
    //     Route::get('/{lead:ulid}', [LeadController::class, 'show'])->name('show');
    //     Route::put('/{lead:ulid}', [LeadController::class, 'update'])->name('update');
    //     Route::post('/{lead:ulid}/convert', [LeadController::class, 'convert'])->name('convert');
    //     Route::patch('/{lead:ulid}/disqualify', [LeadController::class, 'disqualify'])->name('disqualify');
    //     Route::get('/{lead:ulid}/score', ...)->name('score');
    // });

    // ── Opportunities ────────────────────────────────────────────────────
    // TODO: Phase 2 — Opportunity model and OpportunityService not yet implemented.
    // Uncomment when app/Domains/CRM/Models/Opportunity.php and related services are created.
    // Route::prefix('opportunities')->name('opportunities.')->group(function () {
    //     Route::get('/', [OpportunityController::class, 'index'])->name('index');
    //     Route::post('/', [OpportunityController::class, 'store'])->name('store');
    //     Route::get('/pipeline', [OpportunityController::class, 'pipeline'])->name('pipeline');
    //     Route::get('/{opportunity:ulid}', [OpportunityController::class, 'show'])->name('show');
    //     Route::put('/{opportunity:ulid}', [OpportunityController::class, 'update'])->name('update');
    //     Route::patch('/{opportunity:ulid}/close-won', [OpportunityController::class, 'closeWon'])->name('close-won');
    //     Route::patch('/{opportunity:ulid}/close-lost', [OpportunityController::class, 'closeLost'])->name('close-lost');
    // });

    // ── Tickets ──────────────────────────────────────────────────────────
    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('index');
        Route::post('/', [TicketController::class, 'store'])->name('store');
        Route::get('/{ticket:ulid}', [TicketController::class, 'show'])->name('show');
        Route::post('/{ticket:ulid}/reply', [TicketController::class, 'reply'])->name('reply');
        Route::patch('/{ticket:ulid}/assign', [TicketController::class, 'assign'])->name('assign');
        Route::patch('/{ticket:ulid}/resolve', [TicketController::class, 'resolve'])->name('resolve');
        Route::patch('/{ticket:ulid}/close', [TicketController::class, 'close'])->name('close');
        Route::patch('/{ticket:ulid}/reopen', [TicketController::class, 'reopen'])->name('reopen');
    });

    // ── CRM Dashboard / SLA Metrics ──────────────────────────────────────────
    Route::get('dashboard', function (): JsonResponse {
        $today = now()->toDateString();

        $openCount = DB::table('crm_tickets')->where('status', 'open')->count();
        $inProgressCount = DB::table('crm_tickets')->where('status', 'in_progress')->count();
        $resolvedToday = DB::table('crm_tickets')
            ->where('status', 'resolved')
            ->whereDate('updated_at', $today)
            ->count();

        $avgHours = DB::table('crm_tickets')
            ->whereNotNull('resolved_at')
            ->selectRaw('ROUND(AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600)::numeric, 1) as avg_hours')
            ->value('avg_hours') ?? 0;

        $totalResolvable = DB::table('crm_tickets')
            ->whereIn('status', ['resolved', 'closed'])
            ->count();
        $breachedCount = DB::table('crm_tickets')
            ->whereNotNull('sla_breached_at')
            ->count();
        $compliancePct = $totalResolvable > 0
            ? round((($totalResolvable - $breachedCount) / $totalResolvable) * 100, 1)
            : 100;

        $byPriority = DB::table('crm_tickets')
            ->whereIn('status', ['open', 'in_progress'])
            ->selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->get();

        $recentBreaches = DB::table('crm_tickets')
            ->whereNotNull('sla_breached_at')
            ->select('id', 'ulid', 'ticket_number', 'subject', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json(['data' => [
            'open_tickets' => $openCount,
            'in_progress_tickets' => $inProgressCount,
            'resolved_today' => $resolvedToday,
            'avg_resolution_hours' => (float) $avgHours,
            'sla_compliance_pct' => $compliancePct,
            'sla_breached_count' => $breachedCount,
            'tickets_by_priority' => $byPriority,
            'recent_breaches' => $recentBreaches,
        ]]);
    })->name('dashboard');

    // ── Client Portal Orders ────────────────────────────────────────────────
    Route::prefix('client-orders')->name('client-orders.')->group(function () {
        // IMPORTANT: Specific routes MUST come before parameterized routes
        // to prevent Laravel from interpreting "my-orders" as an {order} parameter

        // Client portal routes (specific paths first)
        Route::get('/my-orders', [ClientOrderController::class, 'myOrders'])->name('my-orders');
        Route::post('/', [ClientOrderController::class, 'store'])->name('store');
        Route::get('/products/available', [ClientOrderController::class, 'availableProducts'])->name('products.available');

        // Sales/Staff routes
        Route::get('/', [ClientOrderController::class, 'index'])->name('index')->middleware('can:viewAny,'.ClientOrder::class);

        // Parameterized routes (must be last)
        Route::get('/{order:ulid}', [ClientOrderController::class, 'show'])->name('show');
        Route::put('/{order:ulid}', [ClientOrderController::class, 'update'])->name('update')->middleware('can:update,order');

        // Action routes with rate limiting (uses named limiter defined in AppServiceProvider)
        Route::middleware(['throttle:client-order-actions'])->group(function () {
            Route::post('/{order:ulid}/approve', [ClientOrderController::class, 'approve'])->name('approve')->middleware('can:approve,order');
            Route::post('/{order:ulid}/reject', [ClientOrderController::class, 'reject'])->name('reject')->middleware('can:reject,order');
            Route::post('/{order:ulid}/negotiate', [ClientOrderController::class, 'negotiate'])->name('negotiate')->middleware('can:negotiate,order');
            Route::post('/{order:ulid}/respond', [ClientOrderController::class, 'respond'])->name('respond')->middleware('can:respond,order');
            Route::post('/{order:ulid}/sales-respond', [ClientOrderController::class, 'salesRespond'])->name('sales-respond')->middleware('can:salesRespond,order');
            Route::post('/{order:ulid}/vp-approve', [ClientOrderController::class, 'vpApprove'])->name('vp-approve')->middleware('can:vpApprove,order');
            Route::get('/{order:ulid}/stock-availability', [ClientOrderController::class, 'stockAvailability'])->name('stock-availability');
            Route::post('/{order:ulid}/force-production', [ClientOrderController::class, 'forceProduction'])->name('force-production')->middleware('can:forceProduction,order');
            Route::post('/{order:ulid}/cancel', [ClientOrderController::class, 'cancel'])->name('cancel')->middleware('can:cancel,order');

            // Order tracking timeline (client-facing visibility)
            Route::get('/{order:ulid}/tracking', function (\App\Domains\CRM\Models\ClientOrder $order) {
                $service = app(\App\Domains\CRM\Services\OrderTrackingService::class);
                return response()->json(['data' => $service->track($order)]);
            })->name('tracking');
        });
    });
});
