# Production -> QC -> Delivery Flow Audit

## Flow Overview

```
Client Order (approved)
  -> OrderAutomationService creates Production Orders (draft)
  -> Production Manager releases WO (draft -> released)
     -> BOM stock deducted (PROD-001)
     -> MRQ auto-created from BOM
  -> Production starts (released -> in_progress)
     -> MRQs must be fulfilled first
  -> Output logged (ProductionOutputLog)
  -> QC Inspection (in-process or final)
     -> Failed: order goes on_hold
     -> Passed: order can complete
  -> Production completed (in_progress -> completed)
     -> Finished goods received into stock
     -> ProductionOrderCompleted event fires
     -> Delivery Receipt auto-created (listener)
  -> Delivery Receipt confirmed
     -> Outbound stock issued
  -> Shipment created and tracked
     -> ShipmentDelivered event fires
  -> AR Invoice auto-created
```

---

## CRITICAL BUGS

### BUG-PROD-1: Production actual cost uses non-existent standard_price field

**Severity:** CRITICAL -- already identified in procurement audit and FIXED in PR #60  
**File:** `CostingService.php:335`  
**Status:** Fixed (changes `standard_price` to `standard_price_centavos`)

This was the same `standard_price` column bug found in the procurement audit. The fix in PR #60 covers this.

### BUG-PROD-2: OrderAutomationService has duplicate import

**Severity:** LOW  
**File:** [`OrderAutomationService.php:15`](app/Domains/Production/Services/OrderAutomationService.php:15)

```php
use App\Shared\Contracts\ServiceContract;  // line 12
use App\Shared\Contracts\ServiceContract;  // line 15 -- DUPLICATE
```

PHPStan should catch this but it's harmless.

### BUG-PROD-3: OrderAutomationService doesn't set standard_unit_cost_centavos

**Severity:** MEDIUM  
**File:** [`OrderAutomationService.php:107-121`](app/Domains/Production/Services/OrderAutomationService.php:107)

When auto-creating production orders from client orders, the service doesn't set `standard_unit_cost_centavos` or `estimated_total_cost_centavos`. The regular `ProductionOrderService::store()` calculates these from the BOM's `standard_cost_centavos`, but `OrderAutomationService` skips this and creates orders with default 0 costs.

**Impact:** Auto-created production orders show zero estimated cost. The cost variance analysis (`ProductionCostPostingService`) may fail or produce misleading variances.

---

## HIGH-RISK GAPS

### GAP-PROD-1: Stock deducted on release AND MRQ created -- double deduction risk

**Severity:** HIGH  
**File:** [`ProductionOrderService::release()`](app/Domains/Production/Services/ProductionOrderService.php:238)

On release, TWO things happen:
1. `deductBomComponents()` (line 262) -- directly issues stock via `StockService::issue()` for all BOM components
2. `mrqService->createFromBom()` (line 273) -- creates a Material Requisition which, when fulfilled, ALSO issues stock

If both paths execute and the MRQ is later fulfilled, the same materials are deducted twice.

**Current mitigation:** The MRQ is created as `draft` and requires separate fulfillment. If warehouse staff fulfill the MRQ manually, they'd issue stock again on top of the release deduction.

**Fix needed:** Either:
- Remove the direct `deductBomComponents()` on release and rely solely on MRQ fulfillment, OR
- Don't create MRQ on release (since stock is already deducted), OR
- Mark the MRQ as pre-fulfilled when stock is deducted on release

### GAP-PROD-2: No FQC (Final Quality Control) gate before completion

**Severity:** HIGH  
**File:** [`ProductionOrderService::complete()`](app/Domains/Production/Services/ProductionOrderService.php:428)

The `complete()` method checks `qty_produced > 0` but does NOT check whether a QC inspection has been passed. The QC gate only exists on `release()` (PROD-002 checks for failed inspections), not on completion.

**Impact:** A production order can be completed and finished goods enter stock without any quality inspection. The QC inspection flow (InspectionService) is entirely optional -- no enforcement at completion.

**Expected:** Final QC inspection should pass before finished goods are received into inventory.

### GAP-PROD-3: Production completion fires event but doesn't check if listener succeeds

**Severity:** MEDIUM  
**File:** [`ProductionOrderService::complete()`](app/Domains/Production/Services/ProductionOrderService.php:478)

```php
DB::afterCommit(fn () => ProductionOrderCompleted::dispatch($order->fresh()));
```

If the `CreateDeliveryReceiptOnProductionComplete` listener fails, there's no retry mechanism and no fallback. The production order is completed but no delivery receipt is created.

### GAP-PROD-4: Warehouse location always uses first active -- no FG warehouse concept

**Severity:** MEDIUM  
**File:** [`ProductionOrderService::complete()`](app/Domains/Production/Services/ProductionOrderService.php:445)

```php
$location = WarehouseLocation::where('is_active', true)->first();
```

Same issue as the procurement flow: all finished goods go to the first warehouse location. There should be a "Finished Goods" warehouse or a per-product default location.

---

## QC DOMAIN GAPS

### GAP-QC-1: No mandatory inspection points in the production lifecycle

**Severity:** HIGH

QC inspections are entirely ad-hoc. There's no enforcement that specific items must be inspected at specific stages. The `InspectionService::store()` creates inspections freely with any stage (`iqc`, `in_process`, `final`), but nothing in the production flow REQUIRES these inspections.

**Expected ERP behavior:**
- Items with `requires_iqc = true` must have a passed IQC before GR (this IS enforced)
- Items with FQC requirements should require a passed final inspection before production completion (NOT enforced)
- In-process inspections should be configurable per BOM/routing step (NOT enforced)

### GAP-QC-2: Inspection qty_passed + qty_failed may not equal qty_inspected

**Severity:** LOW  
**File:** [`InspectionService::recordResults()`](app/Domains/QC/Services/InspectionService.php:57)

The `passed` and `failed` counts come from the request parameters, not computed from the results. The `is_conforming` field on results determines the status (passed/failed), but the qty_passed/qty_failed are set independently. There's no validation that `qty_passed + qty_failed == qty_inspected`.

### GAP-QC-3: NCR (Non-Conformance Report) not auto-created on inspection failure

**Severity:** MEDIUM

When an inspection fails, `InspectionFailed` event is dispatched and a listener puts the production order on hold. But no NCR is automatically created. The NCR must be manually created by QC staff, which may be forgotten.

---

## DELIVERY DOMAIN GAPS

### GAP-DEL-1: Delivery performance is hardcoded to 100% on-time

**Severity:** MEDIUM  
**File:** [`DeliveryReceiptService::deliveryPerformance()`](app/Domains/Delivery/Services/DeliveryReceiptService.php:130)

```php
return [
    'total_deliveries' => $total,
    'on_time' => $total, // TODO: compare against schedule expected dates
    'late' => 0,
    'on_time_rate_pct' => $total > 0 ? 100.0 : 0.0,
];
```

Delivery performance metrics always show 100% on-time rate. The TODO comment indicates this is known but unfixed.

### GAP-DEL-2: Outbound delivery doesn't verify QC passed

**Severity:** HIGH  
**File:** [`DeliveryReceiptService::confirm()`](app/Domains/Delivery/Services/DeliveryReceiptService.php:73)

When an outbound delivery receipt is confirmed, stock is issued directly without checking whether the items have passed final QC inspection. Defective products could be shipped to customers.

### GAP-DEL-3: No link between Delivery Receipt and Client Order for fulfillment tracking

**Severity:** MEDIUM

The `DeliveryReceipt` model has `customer_id` and `delivery_schedule_id`, but there's no direct `client_order_id` FK. Tracking which client order a delivery fulfills requires traversing `delivery_schedule -> combined_delivery_schedules -> client_order`, which is fragile.

### GAP-DEL-4: Shipment delivered doesn't trigger AR invoice

**Severity:** HIGH  
**File:** [`ShipmentService::updateStatus()`](app/Domains/Delivery/Services/ShipmentService.php:66)

When a shipment is marked as `delivered`, the `ShipmentDelivered` event fires. However, searching for listeners on this event, the AR invoice auto-creation may not be properly wired (the event exists but the listener chain needs verification).

---

## COST POSTING GAPS

### GAP-COST-1: ProductionCostPostingService uses LIKE queries for GL accounts

**Severity:** HIGH  
**File:** [`ProductionCostPostingService.php:71-80`](app/Domains/Production/Services/ProductionCostPostingService.php:71)

```php
$wipAccount = ChartOfAccount::where('name', 'like', '%Work in Process%')
    ->orWhere('name', 'like', '%WIP%')
    ->first();
$varianceAccount = ChartOfAccount::where('name', 'like', '%Cost Variance%')
    ->orWhere('name', 'like', '%Manufacturing Variance%')
    ->first();
```

GL account resolution uses fuzzy name matching. If account names change or don't match the pattern, the journal entry will have missing lines (silently -- there's no error if the account is null, it just skips the line).

**Impact:** Unbalanced journal entries or missing cost postings.

**Fix:** Use specific account codes (e.g., `1400` for WIP, `5100` for COGS, `5900` for variance) or system settings.

### GAP-COST-2: Cost posting not automatically triggered on production completion

**Severity:** MEDIUM

The `ProductionCostPostingService::postCostVariance()` exists but is never called automatically. It requires manual invocation. The `ProductionOrderCompleted` event dispatches but the listener only creates a delivery receipt -- it doesn't post the cost variance to GL.

---

## CLIENT ORDER -> PRODUCTION GAPS

### GAP-CO-1: No quantity reconciliation between client order and production output

**Severity:** MEDIUM

When a client order spawns production orders, there's no tracking of whether the produced quantity matches the ordered quantity. If production produces less than ordered (due to rejects), the shortfall is not automatically communicated back to the client order.

### GAP-CO-2: Client order status doesn't update on production completion

**Severity:** MEDIUM

The client order stays in `approved` status even after all linked production orders are completed and delivered. There's no listener that updates the client order status to `fulfilled` or `delivered`.

---

## PRIORITY ACTION ITEMS

### Immediate Fixes

| # | Issue | Severity | Effort |
|---|-------|----------|--------|
| 1 | GAP-PROD-1: Fix double stock deduction (release deducts + MRQ deducts) | HIGH | Medium -- decide on single deduction path |
| 2 | GAP-DEL-2: Add QC gate before outbound delivery confirmation | HIGH | Medium |
| 3 | GAP-COST-1: Use account codes instead of LIKE queries for GL accounts | HIGH | Low -- change to code-based lookup |
| 4 | BUG-PROD-3: Set standard_unit_cost on auto-created production orders | MEDIUM | Low |
| 5 | GAP-PROD-2: Add optional FQC gate before production completion | HIGH | Medium |

### Integration Improvements

| # | Feature | Effort |
|---|---------|--------|
| 6 | Auto-trigger cost variance posting on production completion | Medium |
| 7 | Auto-create NCR on inspection failure | Low |
| 8 | Track client order fulfillment through production -> delivery chain | Medium |
| 9 | Fix delivery performance metrics (compare against schedule) | Medium |
| 10 | Verify ShipmentDelivered -> AR Invoice listener chain | Low |
