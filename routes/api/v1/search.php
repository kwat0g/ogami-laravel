<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Global Search — /api/v1/search
|--------------------------------------------------------------------------
| Cross-module search across employees, invoices, vendors, items, POs, etc.
*/

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/', function (Request $request): JsonResponse {
        $q = trim((string) $request->input('q'));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $limit = min(10, $request->integer('limit', 5));
        $pattern = "%{$q}%";
        $results = [];

        // Helper: run a search query safely — if the table/column doesn't exist, skip it
        $safeSearch = function (\Closure $fn) use (&$results): void {
            try {
                $results = array_merge($results, $fn()->all());
            } catch (\Throwable) {
                // Skip this module if table/column missing
            }
        };

        // Employees
        $safeSearch(fn () => DB::table('employees')
            ->where(function ($qb) use ($pattern) {
                $qb->where('first_name', 'ilike', $pattern)
                    ->orWhere('last_name', 'ilike', $pattern)
                    ->orWhere('employee_code', 'ilike', $pattern);
            })
            ->select('ulid', 'employee_code as code', DB::raw("concat(first_name, ' ', last_name) as label"))
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['module' => 'HR', 'type' => 'Employee', 'label' => "{$r->code} — {$r->label}", 'url' => "/hr/employees/{$r->ulid}"]));

        // Vendors
        $safeSearch(fn () => DB::table('vendors')
            ->where(function ($qb) use ($pattern) {
                $qb->where('name', 'ilike', $pattern)
                    ->orWhere('tin', 'ilike', $pattern);
            })
            ->select('id', 'name', 'tin')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['module' => 'AP', 'type' => 'Vendor', 'label' => $r->name . ($r->tin ? " ({$r->tin})" : ''), 'url' => "/accounting/vendors/{$r->id}"]));

        // Customers
        $safeSearch(fn () => DB::table('customers')
            ->where('name', 'ilike', $pattern)
            ->select('id', 'name')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['module' => 'AR', 'type' => 'Customer', 'label' => $r->name, 'url' => "/ar/customers/{$r->id}"]));

        // Items
        $safeSearch(fn () => DB::table('item_masters')
            ->where(function ($qb) use ($pattern) {
                $qb->where('name', 'ilike', $pattern)
                    ->orWhere('item_code', 'ilike', $pattern);
            })
            ->select('ulid', 'item_code as code', 'name')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['module' => 'Inventory', 'type' => 'Item', 'label' => "{$r->code} — {$r->name}", 'url' => "/inventory/items/{$r->ulid}"]));

        // Purchase Orders
        $safeSearch(fn () => DB::table('purchase_orders')
            ->where('po_number', 'ilike', $pattern)
            ->select('ulid', 'po_number as code', 'status')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['module' => 'Procurement', 'type' => 'PO', 'label' => "{$r->code} ({$r->status})", 'url' => "/procurement/purchase-orders/{$r->ulid}"]));

        // Equipment
        $safeSearch(fn () => DB::table('equipment')
            ->where(function ($qb) use ($pattern) {
                $qb->where('name', 'ilike', $pattern)
                    ->orWhere('asset_tag', 'ilike', $pattern);
            })
            ->select('ulid', 'asset_tag as code', 'name')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['module' => 'Maintenance', 'type' => 'Equipment', 'label' => ($r->code ? "{$r->code} — " : '').$r->name, 'url' => "/maintenance/equipment/{$r->ulid}"]));

        return response()->json(['data' => $results]);
    })->name('global-search');
});
