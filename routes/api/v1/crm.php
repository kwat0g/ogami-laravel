<?php

declare(strict_types=1);

use App\Http\Controllers\CRM\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CRM Module Routes — /api/v1/crm/
|--------------------------------------------------------------------------
| Accessible by: staff with crm.tickets.* permissions AND client role users.
| Ticket scoping for clients is enforced in TicketPolicy and TicketService.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/',                          [TicketController::class, 'index'])->name('index');
        Route::post('/',                         [TicketController::class, 'store'])->name('store');
        Route::get('/{ticket:ulid}',             [TicketController::class, 'show'])->name('show');
        Route::post('/{ticket:ulid}/reply',      [TicketController::class, 'reply'])->name('reply');
        Route::patch('/{ticket:ulid}/assign',    [TicketController::class, 'assign'])->name('assign');
        Route::patch('/{ticket:ulid}/resolve',   [TicketController::class, 'resolve'])->name('resolve');
        Route::patch('/{ticket:ulid}/close',     [TicketController::class, 'close'])->name('close');
        Route::patch('/{ticket:ulid}/reopen',    [TicketController::class, 'reopen'])->name('reopen');
    });

    // ── CRM Dashboard / SLA Metrics ──────────────────────────────────────────
    Route::get('dashboard', function (): \Illuminate\Http\JsonResponse {
        $today = now()->toDateString();

        $openCount = \Illuminate\Support\Facades\DB::table('tickets')->where('status', 'open')->count();
        $inProgressCount = \Illuminate\Support\Facades\DB::table('tickets')->where('status', 'in_progress')->count();
        $resolvedToday = \Illuminate\Support\Facades\DB::table('tickets')
            ->where('status', 'resolved')
            ->whereDate('updated_at', $today)
            ->count();

        $avgHours = \Illuminate\Support\Facades\DB::table('tickets')
            ->whereNotNull('resolved_at')
            ->selectRaw("ROUND(AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600)::numeric, 1) as avg_hours")
            ->value('avg_hours') ?? 0;

        $totalResolvable = \Illuminate\Support\Facades\DB::table('tickets')
            ->whereIn('status', ['resolved', 'closed'])
            ->count();
        $breachedCount = \Illuminate\Support\Facades\DB::table('tickets')
            ->where('sla_breached', true)
            ->count();
        $compliancePct = $totalResolvable > 0
            ? round((($totalResolvable - $breachedCount) / $totalResolvable) * 100, 1)
            : 100;

        $byPriority = \Illuminate\Support\Facades\DB::table('tickets')
            ->whereIn('status', ['open', 'in_progress'])
            ->selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->get();

        $recentBreaches = \Illuminate\Support\Facades\DB::table('tickets')
            ->where('sla_breached', true)
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
});
