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
});
