# Procurement Module -- Gap Analysis and Audit

## Executive Summary

The Procurement module is the most mature module audited so far. It has thorough authorization (46 `authorize` calls), proper SoD enforcement, comprehensive workflow steps (PR -> PO -> GR -> QC), and well-built frontend pages with StatusTimeline, ConfirmDialog, and proper error handling in mutation callbacks.

The main issue is the same `_err` runtime crash bug found in Inventory, affecting 15 catch blocks across 6 files.

---

## CRITICAL BUG -- Will Crash at Runtime

### BUG-01: `_err` vs `err` variable name mismatch in 15 catch blocks
- **Files affected (6)**: `VendorRfqListPage.tsx` (2), `VendorRfqDetailPage.tsx` (5), `PurchaseRequestDetailPage.tsx` (5), `CreatePurchaseRequestPage.tsx` (1), `CreatePurchaseOrderPage.tsx` (1), `CreateGoodsReceiptPage.tsx` (1)
- **Problem**: Same as Inventory -- `catch (_err)` declares unused var but body references `err`.
- **Impact**: All error handlers in these pages crash with `ReferenceError`.
- **Fix**: Change `_err` to `err` in all 15 occurrences.

---

## MEDIUM GAPS

### GAP-01: Dead CreatePurchaseOrderPage route
- **Location**: Route `/procurement/purchase-orders/new` maps to `CreatePurchaseOrderPage`
- **Problem**: The backend `PurchaseOrderController::store()` always returns `abort(403, 'Manual Purchase Order creation is disabled')`. POs are auto-created from approved PRs. The route and page are misleading.
- **Impact**: Users who navigate to the "Create PO" page get a confusing 403 error.
- **Fix**: Either remove the route or show a clear message on the page explaining POs are auto-created.

### GAP-02: Payment batch pages have no authorization checks in inline route closures
- **Location**: [`procurement.php:392-424`](routes/api/v1/procurement.php:392) payment batch routes
- **Problem**: The payment batch endpoints use inline closures without any `abort_unless` or policy checks. Any user with procurement module access can create, submit, approve, and process payment batches.
- **Fix**: Add permission checks to payment batch closures.

### GAP-03: Budget pre-check endpoint has no authorization
- **Location**: [`procurement.php:102-159`](routes/api/v1/procurement.php:102)
- **Problem**: The inline budget check closure has no authorization. Any authenticated user with procurement access can query any department's budget info.
- **Fix**: Add permission check.

---

## LOW-SEVERITY NOTES

- The module properly implements SoD via middleware (`sod:inventory_mrq,note`) on MRQ routes
- PR workflow has batch review/reject endpoints (well-built)
- GR has full QC integration (submit-for-qc, accept-with-defects, return-to-supplier, resubmit-for-qc)
- Vendor RFQ flow is complete (create, send, receive quotes, close, award)
- PDF generation available for PR and PO
- Procurement analytics and vendor scorecard are functional

## Recommended Fix Priority

### Phase 1 -- Critical Bug
- [ ] BUG-01: Fix `_err` -> `err` in 15 catch blocks (6 files)

### Phase 2 -- Authorization
- [ ] GAP-02: Add auth to payment batch inline routes
- [ ] GAP-03: Add auth to budget pre-check endpoint

### Phase 3 -- UX Cleanup
- [ ] GAP-01: Remove or redirect dead CreatePurchaseOrderPage route
