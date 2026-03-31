# Vendor Portal Audit and Improvement Plan

## Audit Summary

The vendor portal is **more complete than initially expected**. It has 7 frontend pages, full order management (acknowledge, negotiate, mark in-transit, deliver with partial delivery support), GR visibility with QC/3-way match/invoice flags, invoice submission against confirmed GRs, and catalog item management with bulk import.

**Overall Assessment: GOOD -- quality-of-life improvements needed, no critical bugs.**

---

## What Already Works Well

| Feature | Status |
|---------|--------|
| Order list with status filtering | Working |
| Order detail with fulfillment history, items, split PO tracking | Working |
| Acknowledge PO / Propose changes / Negotiate | Working |
| Mark in-transit / Mark delivered with per-item quantities | Working |
| Partial delivery with auto-split PO | Working |
| GR list with QC status, 3-way match flag, invoice flag | Working |
| Invoice submission against confirmed GR | Working |
| Catalog items CRUD + bulk import | Working |
| Dashboard with active orders and catalog stats | Working |

---

## Findings (Quality-of-Life Gaps)

### Finding 1: GR Status Badges Incomplete

**Location**: `VendorGoodsReceiptsPage.tsx` line 64-68

The `StatusBadge` component only has colors for `draft` and `confirmed`. Missing colors for: `submitted`, `pending_qc`, `qc_passed`, `qc_failed`, `returned`, `cancelled`.

**Impact**: Vendors see an unstyled badge when GR is in QC or other states.

**Fix**: Add all GR status colors matching the backend state machine.

### Finding 2: No GR Detail Page

**Location**: `VendorGoodsReceiptsPage.tsx` -- table rows are not clickable

Vendors can see the GR list but cannot click into a GR to view:
- Item-level QC results (passed/failed per item)
- Accepted vs rejected quantities
- QC inspector notes
- NCR references if items failed

**Impact**: Vendor calls warehouse asking "what failed QC?" because they can't see it on the portal.

**Fix**: Create a `VendorGoodsReceiptDetailPage.tsx` with item-level QC status display. Backend already loads `goods_receipts` relation with items -- need a new `/vendor-portal/goods-receipts/{id}` route.

### Finding 3: No Invoice Detail / Payment Tracking

**Location**: `VendorInvoicesPage.tsx` -- invoice list shows status but no detail view

Vendors can see their submitted invoices but cannot:
- See approval status progression (draft -> submitted -> approved -> paid)
- See payment date and amount when paid
- See rejection reason if rejected
- Track when payment was disbursed

**Impact**: Vendor asks "when will I get paid?" -- they can't see payment status on the portal.

**Fix**: Add invoice detail view showing approval timeline, payment history, and expected payment date.

### Finding 4: Dashboard Lacks Post-Delivery Visibility

**Location**: `VendorPortalDashboardPage.tsx` line 10-12

Dashboard only filters active orders by `sent` and `partially_received` statuses. It misses:
- Orders `in_transit` (vendor dispatched but not yet received)
- Orders `delivered` (awaiting warehouse confirmation)
- Pending GR confirmations
- Pending invoice payments
- Total receivables (unpaid invoices)

**Fix**: Add more stat cards: "Awaiting Receipt Confirmation", "Pending Invoices", "Total Receivables". Add a "Recent Activity" section.

### Finding 5: No Notification/Alert System for Vendors

Currently the vendor portal has no way to alert vendors about:
- GR confirmed (you can now submit your invoice)
- QC failed (items rejected, action needed)
- Invoice approved (payment scheduled)
- Payment disbursed

**Fix**: Add a notifications section or alert banners similar to what we added to the client portal dashboard.

---

## Implementation Priority

### Quick Wins (frontend-only, no backend changes)

- [ ] **Fix GR status badge colors** -- add missing status colors for all backend GR states
- [ ] **Fix dashboard active orders filter** -- include `in_transit`, `delivered`, `acknowledged` in active count
- [ ] **Add dashboard stat cards** -- pending GR confirmations, pending invoice payments, total receivables

### Medium Effort (new page + minor backend route)

- [ ] **Create GR detail page** -- show item-level QC results, acceptance quantities, NCR links
  - Backend: Add `GET /vendor-portal/goods-receipts/{id}` route with item-level QC data
  - Frontend: New `VendorGoodsReceiptDetailPage.tsx` with clickable rows from list
- [ ] **Create invoice detail page** -- show approval timeline, payment history
  - Backend: Existing AP invoice data is sufficient, add vendor-scoped detail route
  - Frontend: New page with payment tracking display

### Larger Effort (notifications)

- [ ] **Add vendor notification banners** -- alert when GR confirmed, QC failed, payment made
  - Backend: Wire vendor notifications from existing events (ThreeWayMatchPassed, InspectionFailed, etc.)
  - Frontend: Add notification section to vendor dashboard

---

## Assessment vs Client Portal

| Capability | Client Portal | Vendor Portal |
|------------|---------------|---------------|
| Order placement | Yes (shop page) | N/A (PO comes from company) |
| Order tracking | Yes (status display + tracking) | Yes (PO status + fulfillment history) |
| Negotiation | Yes (bidirectional) | Yes (propose changes) |
| Delivery management | Acknowledge receipt | Mark in-transit, deliver, partial delivery |
| Post-delivery visibility | Dispatched/delivered/fulfilled display | GR list with QC/3-way match flags |
| Invoice visibility | N/A | Invoice submission + list |
| Payment tracking | N/A | Not yet -- gap |
| Detail pages | Order detail, delivery receipt | Order detail only -- GR/Invoice detail missing |
| Notifications | Dashboard alerts (new) | Not yet -- gap |

The vendor portal is functionally deeper than the client portal in procurement workflow, but lacks the detail-level drill-down and notification features that make it feel polished.
