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

        // Employees
        $employees = DB::table('employees')
            ->where(function ($qb) use ($pattern) {
                $qb->where('first_name', 'ilike', $pattern)
                    ->orWhere('last_name', 'ilike', $pattern)
                    ->orWhere('employee_code', 'ilike', $pattern);
            })
            ->select('ulid', 'employee_code as code', DB::raw("concat(first_name, ' ', last_name) as label"))
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['module' => 'HR', 'type' => 'Employee', 'label' => "{$r->code} — {$r->label}", 'url' => "/hr/employees/{$r->ulid}"]);
        $results = array_merge($results, $employees->all());

        // Vendors
        $vendors = DB::table('vendors')
            ->where(function ($qb) use ($pattern) {
                $qb->where('company_name', 'ilike', $pattern)
                    ->orWhere('vendor_code', 'ilike', $pattern);
            })
            ->select('ulid', 'vendor_code as code', 'company_name as name')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['module' => 'AP', 'type' => 'Vendor', 'label' => "{$r->code} — {$r->name}", 'url' => "/accounting/vendors/{$r->ulid}"]);
        $results = array_merge($results, $vendors->all());

        // Customers
        $customers = DB::table('customers')
            ->where('company_name', 'ilike', $pattern)
            ->select('ulid', 'company_name as name')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['module' => 'AR', 'type' => 'Customer', 'label' => $r->name, 'url' => "/ar/customers/{$r->ulid}"]);
        $results = array_merge($results, $customers->all());

        // Items
        $items = DB::table('item_masters')
            ->where(function ($qb) use ($pattern) {
                $qb->where('name', 'ilike', $pattern)
                    ->orWhere('item_code', 'ilike', $pattern);
            })
            ->select('ulid', 'item_code as code', 'name')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['module' => 'Inventory', 'type' => 'Item', 'label' => "{$r->code} — {$r->name}", 'url' => "/inventory/items/{$r->ulid}"]);
        $results = array_merge($results, $items->all());

        // Purchase Orders
        $pos = DB::table('purchase_orders')
            ->where('po_number', 'ilike', $pattern)
            ->select('ulid', 'po_number as code', 'status')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['module' => 'Procurement', 'type' => 'PO', 'label' => "{$r->code} ({$r->status})", 'url' => "/procurement/purchase-orders/{$r->ulid}"]);
        $results = array_merge($results, $pos->all());

        // Equipment
        $equipment = DB::table('equipment')
            ->where(function ($qb) use ($pattern) {
                $qb->where('name', 'ilike', $pattern)
                    ->orWhere('asset_tag', 'ilike', $pattern);
            })
            ->select('ulid', 'asset_tag as code', 'name')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['module' => 'Maintenance', 'type' => 'Equipment', 'label' => ($r->code ? "{$r->code} — " : '').$r->name, 'url' => "/maintenance/equipment/{$r->ulid}"]);
        $results = array_merge($results, $equipment->all());

        return response()->json(['data' => $results]);
    })->name('global-search');
});
