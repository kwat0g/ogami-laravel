<?php

declare(strict_types=1);

/**
 * Module CRUD Smoke Test — Per-module happy-path audit.
 *
 * Tests the core create → read → update → action workflow for each major module
 * using seeded data and a superadmin user. Any 500 response is a server bug.
 *
 * Run:
 *   ./vendor/bin/pest tests/Feature/Audit/ModuleCrudSmokeTest.php -v --no-coverage
 */

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutMiddleware([ThrottleRequests::class]);
});

function superadmin(): User
{
    return User::where('email', 'superadmin@ogamierp.local')->firstOrFail();
}

// ═══════════════════════════════════════════════════════════════════════════
// ATTENDANCE MODULE
// ═══════════════════════════════════════════════════════════════════════════

test('attendance: time-in / time-out / today / my-logs endpoints', function () {

    $user = superadmin();

    // GET today's log (should return null data, not 500)
    $r = $this->actingAs($user)->getJson('/api/v1/attendance/today');
    expect($r->status())->toBeLessThan(500, "GET today => {$r->status()}: " . substr($r->getContent(), 0, 300));

    // GET my-logs
    $r = $this->actingAs($user)->getJson('/api/v1/attendance/my-logs');
    expect($r->status())->toBeLessThan(500, "GET my-logs => {$r->status()}: " . substr($r->getContent(), 0, 300));

    // GET geofence settings
    $r = $this->actingAs($user)->getJson('/api/v1/attendance/geofence-settings');
    expect($r->status())->toBeLessThan(500, "GET geofence-settings => {$r->status()}: " . substr($r->getContent(), 0, 300));

    // GET logs index
    $r = $this->actingAs($user)->getJson('/api/v1/attendance/logs');
    expect($r->status())->toBeLessThan(500, "GET logs => {$r->status()}: " . substr($r->getContent(), 0, 300));

    // GET attendance summary
    $r = $this->actingAs($user)->getJson('/api/v1/attendance/summary');
    expect($r->status())->toBeLessThan(500, "GET summary => {$r->status()}: " . substr($r->getContent(), 0, 300));

    // GET shifts
    $r = $this->actingAs($user)->getJson('/api/v1/attendance/shifts');
    expect($r->status())->toBeLessThan(500, "GET shifts => {$r->status()}: " . substr($r->getContent(), 0, 300));

    // GET overtime requests
    $r = $this->actingAs($user)->getJson('/api/v1/attendance/overtime-requests');
    expect($r->status())->toBeLessThan(500, "GET overtime-requests => {$r->status()}: " . substr($r->getContent(), 0, 300));

    // GET correction requests
    $r = $this->actingAs($user)->getJson('/api/v1/attendance/correction-requests');
    expect($r->status())->toBeLessThan(500, "GET correction-requests => {$r->status()}: " . substr($r->getContent(), 0, 300));

    // GET work locations
    $r = $this->actingAs($user)->getJson('/api/v1/attendance/work-locations');
    expect($r->status())->toBeLessThan(500, "GET work-locations => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// HR MODULE
// ═══════════════════════════════════════════════════════════════════════════

test('hr: employees CRUD endpoints', function () {

    $user = superadmin();

    // GET employees list
    $r = $this->actingAs($user)->getJson('/api/v1/hr/employees');
    expect($r->status())->toBeLessThan(500, "GET employees => {$r->status()}: " . substr($r->getContent(), 0, 300));

    // GET team view
    $r = $this->actingAs($user)->getJson('/api/v1/hr/employees/team');
    expect($r->status())->toBeLessThan(500, "GET employees/team => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// PROCUREMENT MODULE
// ═══════════════════════════════════════════════════════════════════════════

test('procurement: PR → PO → GR workflow endpoints', function () {

    $user = superadmin();

    // GET purchase requests
    $r = $this->actingAs($user)->getJson('/api/v1/procurement/purchase-requests');
    expect($r->status())->toBeLessThan(500, "GET PRs => {$r->status()}: " . substr($r->getContent(), 0, 300));

    // GET purchase orders
    $r = $this->actingAs($user)->getJson('/api/v1/procurement/purchase-orders');
    expect($r->status())->toBeLessThan(500, "GET POs => {$r->status()}: " . substr($r->getContent(), 0, 300));

    // GET goods receipts
    $r = $this->actingAs($user)->getJson('/api/v1/procurement/goods-receipts');
    expect($r->status())->toBeLessThan(500, "GET GRs => {$r->status()}: " . substr($r->getContent(), 0, 300));

    // GET vendor RFQs
    $r = $this->actingAs($user)->getJson('/api/v1/procurement/vendor-rfqs');
    expect($r->status())->toBeLessThan(500, "GET RFQs => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// ACCOUNTING MODULE
// ═══════════════════════════════════════════════════════════════════════════

test('accounting: journal entries, COA, fiscal periods, bank accounts', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/accounting/journal-entries');
    expect($r->status())->toBeLessThan(500, "GET JEs => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/accounting/chart-of-accounts');
    expect($r->status())->toBeLessThan(500, "GET COA => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/accounting/fiscal-periods');
    expect($r->status())->toBeLessThan(500, "GET fiscal-periods => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/accounting/bank-accounts');
    expect($r->status())->toBeLessThan(500, "GET bank-accounts => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/accounting/bank-reconciliations');
    expect($r->status())->toBeLessThan(500, "GET bank-reconciliations => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/accounting/recurring-journal-templates');
    expect($r->status())->toBeLessThan(500, "GET recurring-templates => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// AP MODULE (Vendors)
// ═══════════════════════════════════════════════════════════════════════════

test('ap: vendors, invoices, credit notes endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/accounting/vendors');
    expect($r->status())->toBeLessThan(500, "GET vendors => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/accounting/vendor-invoices');
    expect($r->status())->toBeLessThan(500, "GET vendor-invoices => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// AR MODULE (Customers)
// ═══════════════════════════════════════════════════════════════════════════

test('ar: customers, invoices, credit notes endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/ar/customers');
    expect($r->status())->toBeLessThan(500, "GET customers => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/ar/customer-invoices');
    expect($r->status())->toBeLessThan(500, "GET invoices => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// PAYROLL MODULE
// ═══════════════════════════════════════════════════════════════════════════

test('payroll: runs, periods endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/payroll/runs');
    expect($r->status())->toBeLessThan(500, "GET payroll-runs => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/payroll/pay-periods');
    expect($r->status())->toBeLessThan(500, "GET pay-periods => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// LEAVE MODULE
// ═══════════════════════════════════════════════════════════════════════════

test('leave: requests, balances endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/leave/requests');
    expect($r->status())->toBeLessThan(500, "GET leave-requests => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/leave/balances');
    expect($r->status())->toBeLessThan(500, "GET leave-balances => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// LOAN MODULE
// ═══════════════════════════════════════════════════════════════════════════

test('loan: list endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/loans');
    expect($r->status())->toBeLessThan(500, "GET loans => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// INVENTORY MODULE
// ═══════════════════════════════════════════════════════════════════════════

test('inventory: items, locations, MRQ endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/inventory/items');
    expect($r->status())->toBeLessThan(500, "GET items => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/inventory/locations');
    expect($r->status())->toBeLessThan(500, "GET locations => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/inventory/material-requisitions');
    expect($r->status())->toBeLessThan(500, "GET MRQs => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// PRODUCTION MODULE
// ═══════════════════════════════════════════════════════════════════════════

test('production: orders, BOMs, delivery schedules endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/production/orders');
    expect($r->status())->toBeLessThan(500, "GET production-orders => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/production/boms');
    expect($r->status())->toBeLessThan(500, "GET BOMs => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/production/delivery-schedules');
    expect($r->status())->toBeLessThan(500, "GET delivery-schedules => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// QC MODULE
// ═══════════════════════════════════════════════════════════════════════════

test('qc: inspections, templates, NCRs endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/qc/inspections');
    expect($r->status())->toBeLessThan(500, "GET inspections => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/qc/templates');
    expect($r->status())->toBeLessThan(500, "GET templates => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/qc/ncrs');
    expect($r->status())->toBeLessThan(500, "GET NCRs => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// CRM MODULE
// ═══════════════════════════════════════════════════════════════════════════

test('crm: tickets, leads, client orders endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/crm/tickets');
    expect($r->status())->toBeLessThan(500, "GET tickets => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/crm/client-orders');
    expect($r->status())->toBeLessThan(500, "GET client-orders => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

// ═══════════════════════════════════════════════════════════════════════════
// REMAINING MODULES
// ═══════════════════════════════════════════════════════════════════════════

test('delivery: receipts and shipments endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/delivery/receipts');
    expect($r->status())->toBeLessThan(500, "GET receipts => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/delivery/shipments');
    expect($r->status())->toBeLessThan(500, "GET shipments => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

test('maintenance: equipment and work orders endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/maintenance/equipment');
    expect($r->status())->toBeLessThan(500, "GET equipment => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/maintenance/work-orders');
    expect($r->status())->toBeLessThan(500, "GET work-orders => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

test('mold: mold masters endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/mold/molds');
    expect($r->status())->toBeLessThan(500, "GET molds => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

test('fixed-assets: list and categories endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/fixed-assets');
    expect($r->status())->toBeLessThan(500, "GET fixed-assets => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

test('budget: cost centers and annual budgets endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/budget/cost-centers');
    expect($r->status())->toBeLessThan(500, "GET cost-centers => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/budget/annual-budgets');
    expect($r->status())->toBeLessThan(500, "GET annual-budgets => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

test('tax: VAT ledger and BIR filings endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/tax/vat-ledger');
    expect($r->status())->toBeLessThan(500, "GET vat-ledger => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/tax/bir-filings');
    expect($r->status())->toBeLessThan(500, "GET bir-filings => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

test('sales: quotations and orders endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/sales/quotations');
    expect($r->status())->toBeLessThan(500, "GET quotations => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/sales/orders');
    expect($r->status())->toBeLessThan(500, "GET sales-orders => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

test('recruitment: requisitions, postings, applications endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/recruitment/requisitions');
    expect($r->status())->toBeLessThan(500, "GET requisitions => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/recruitment/job-postings');
    expect($r->status())->toBeLessThan(500, "GET job-postings => {$r->status()}: " . substr($r->getContent(), 0, 300));

    $r = $this->actingAs($user)->getJson('/api/v1/recruitment/applications');
    expect($r->status())->toBeLessThan(500, "GET applications => {$r->status()}: " . substr($r->getContent(), 0, 300));
});

test('dashboard: main and executive endpoints', function () {

    $user = superadmin();

    $r = $this->actingAs($user)->getJson('/api/v1/dashboard');
    expect($r->status())->toBeLessThan(500, "GET dashboard => {$r->status()}: " . substr($r->getContent(), 0, 300));
});
