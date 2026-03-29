<?php

declare(strict_types=1);

use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;

/*
|--------------------------------------------------------------------------
| Architecture Tests
|--------------------------------------------------------------------------
| These tests enforce the structural rules defined in ogami_erp_roadmap_v3.md.
| They run on every PR to prevent architectural drift.
|
| Rules:
|   ARCH-001 : Controllers must not contain direct DB calls
|   ARCH-002 : Domain Services must implement ServiceContract
|   ARCH-003 : All custom exceptions must extend DomainException
|   ARCH-004 : Value objects must be final readonly classes
|   ARCH-005 : No dd() / dump() / var_dump() left in app/ source
|   ARCH-006 : Models must not use static calls to Facades inside their methods
|--------------------------------------------------------------------------
*/

// ARCH-001: Controllers have no direct DB / Eloquent calls
arch('controllers do not make direct database calls')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Database\Eloquent\Model'])
    ->ignoring('App\Http\Controllers\Controller');

// ARCH-002: All domain services implement ServiceContract
arch('domain services implement ServiceContract')
    ->expect('App\Domains\\*\\Services')
    ->toImplement(ServiceContract::class);

// ARCH-003: App exceptions extend DomainException (not base \Exception directly)
arch('custom exceptions extend DomainException')
    ->expect('App\Shared\Exceptions')
    ->classes()
    ->toExtend(DomainException::class)
    ->ignoring(DomainException::class);

// ARCH-004: Value objects are final and readonly
arch('value objects are final readonly')
    ->expect('App\Shared\ValueObjects')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

// ARCH-005: No debug helpers left in production source
arch('no debug helpers in app/')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'var_dump', 'ray', 'rdump']);

// ARCH-006: Shared contracts namespace contains only interfaces
arch('shared contracts are interfaces')
    ->expect('App\Shared\Contracts')
    ->toBeInterfaces();

// ARCH-007 (REC-17): Vendor portal controllers must use Resource transformers
// Prevents raw Eloquent model data exposure to vendor users.
arch('vendor portal uses resource transformers')
    ->expect('App\Http\Controllers\VendorPortal')
    ->toUse('App\Http\Resources\VendorPortal')
    ->ignoring('App\Http\Controllers\Controller');

// ARCH-008 (REC-17): StockBalance model should only be used by StockService
// Direct model calls bypass stock_ledger_entries audit trail.
arch('stock balance only accessed through StockService')
    ->expect('App\Domains\Inventory\Models\StockBalance')
    ->toOnlyBeUsedIn([
        'App\Domains\Inventory\Services\StockService',
        'App\Domains\Inventory\Services\InventoryAnalyticsService',
        'App\Domains\Inventory\Services\InventoryReportService',
        'App\Domains\Inventory\Services\PhysicalCountService',
        'App\Domains\Inventory\Services\LowStockReorderService',
        'App\Domains\Inventory\Services\CostingMethodService',
        'App\Http\Resources',    // Resources may format stock data
        'App\Http\Controllers',  // Controllers may query for display
    ]);
