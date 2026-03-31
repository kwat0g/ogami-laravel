# Cross-Module Workflow Audit -- Chain Process and Single Source of Truth

## Audit Scope

Checked all module detail pages for:
1. **ChainRecordTimeline presence** -- is the document chain visible?
2. **Manual creation buttons** that break single source of truth -- should records flow automatically?
3. **Cross-module status sync** -- does completing one step automatically trigger the next?
4. **Frontend type alignment** -- do TypeScript types match backend state machines?

---

## Finding 1: ChainRecordTimeline Missing on 5 Detail Pages

The `ChainRecordTimeline` component exists and works well -- it traces the full document chain via the backend `ChainRecordService`. But it is only used on 4 out of 9 detail pages:

| Detail Page | Has ChainRecordTimeline? | Impact |
|-------------|-------------------------|--------|
| Purchase Request Detail | YES | Can see PR -> PO chain |
| Purchase Order Detail | YES | Can see PR -> PO -> GR -> AP Invoice chain |
| Goods Receipt Detail | YES | Can see PO -> GR -> Inspection chain |
| Production Order Detail | YES | Can see DS -> PO -> MRQ chain |
| **Customer Invoice Detail** | **NO** | Cannot see DR -> Invoice chain |
| **AP Invoice Detail** | **NO** (has inline links instead) | Inconsistent with other pages |
| **Delivery Receipt Detail** | **NO** | Cannot see DS -> DR -> Invoice chain |
| **Sales Order Detail** (internal) | **NO** | Cannot see SO -> Production -> DR chain |
| **Client Order Detail** (sales review) | **NO** | Cannot see CO -> DS -> PO -> DR chain |

**Fix**: Add `<ChainRecordTimeline>` to all 5 missing detail pages. The backend `ChainRecordService` already supports all these document types -- it is just the frontend component that is not mounted.

---

## Finding 2: Manual DR Creation on Delivery Schedule Detail Page

**Location**: `DeliveryScheduleDetailPage.tsx` line 286-292

The Delivery Schedule detail page has a **"Create Delivery Receipt"** button that links to `/delivery/receipts/new?delivery_schedule_id=...`. This forces the user to manually navigate to a different module and fill in a form to create a DR -- but the backend ALREADY auto-creates DRs when:
- A production order completes (`CreateDeliveryReceiptOnProductionComplete`)
- OQC passes (`CreateDeliveryReceiptOnOqcPass`)
- A Sales Order is confirmed (`CreateDeliveryReceiptOnSoConfirm`)
- CDS/DS is dispatched (our new `dispatchSchedule()` method)

**Problem**: The manual button creates a duplicate path that bypasses the chain. A user could click "Create Delivery Receipt" manually AND the auto-creation listener could also fire, resulting in duplicate DRs.

**Fix**: Replace the manual "Create DR" button with either:
- A "Dispatch" button that calls the DS workflow dispatch endpoint (which auto-creates the DR)
- A read-only "Delivery Receipt" link that shows the auto-created DR when it exists

---

## Finding 3: Manual Shipment Creation on Shipments Page

**Location**: `ShipmentsPage.tsx` line 211-215

The Shipments page has a standalone "Create Shipment" form where users manually select a DR and fill in carrier/tracking info. But with our Phase 4 changes, shipments should be created via the "Prepare Shipment" step on the DR detail page (which is part of the workflow).

**Problem**: Two paths to create shipments -- the manual form and the workflow step. The manual form does NOT enforce the workflow (no DR status validation, no vehicle assignment to DR, no ClientOrder sync).

**Fix**: Deprecate the standalone "Create Shipment" form. Shipments should only be created through the DR detail page "Prepare Shipment" workflow step. The Shipments page should be a read-only tracking dashboard.

---

## Finding 4: Manual AR Invoice Creation Form

**Location**: `CustomerInvoiceFormPage.tsx`

There is a standalone "Create Invoice (Draft)" form page where users manually enter customer, amounts, and GL accounts. But AR invoices should flow automatically from:
- Delivery Receipt delivered -> `CreateCustomerInvoiceOnShipmentDelivered` (auto-draft)
- DS client acknowledgment -> `acknowledgeReceipt()` auto-invoice
- `InvoiceAutoDraftService::createFromDeliveryReceipt()` (auto-draft from DR)

**Problem**: The manual form exists for service invoices (non-delivery) which is legitimate, but it is also used for product invoices where it should flow from the delivery chain. The form does not enforce `delivery_receipt_id` linkage for product invoices.

**Fix**: Keep the manual form for service invoices only. For product invoices, the form should be pre-populated from the DR/delivery chain and require a `delivery_receipt_id`. Add a check: if `invoice_type = product` and no `delivery_receipt_id`, show a warning.

---

## Finding 5: Manual AP Invoice Creation Form

**Location**: `APInvoicesPage.tsx` line 95-97, `APInvoiceFormPage.tsx`

AP invoices have both:
- **Auto-creation** via `CreateApInvoiceOnThreeWayMatch` (fires after GR confirmed + 3-way match passes)
- **Manual creation** via "Create Invoice from PO" modal and standalone form

**Problem**: The manual creation path exists for direct invoicing (no GR required), but it creates a duplicate path for the standard procurement flow. A user could manually create an AP invoice for a PO that already has an auto-created one.

**Assessment**: This is actually intentional -- the manual path is for service POs, credit notes, and corrections. The auto-creation has idempotency guards. LOW priority.

---

## Finding 6: Sales Order Status Jump

**Location**: `UpdateSalesOrderOnProductionComplete.php` line 44

When production completes, the Sales Order jumps directly to `delivered` -- but the SalesOrder state machine has `partially_delivered` and `delivered` as distinct states. The jump skips the actual delivery process.

**Fix**: Change the listener to transition to a `production_complete` or `ready_for_delivery` state. Wire the actual delivery event to transition to `delivered`.

---

## Finding 7: Frontend TypeScript Types Out of Sync

| Type | Issue |
|------|-------|
| `PurchaseOrderStatus` | Missing `delivered` -- exists in backend state machine |
| `GoodsReceiptStatus` | Has `rejected` and `partial_accept` not in backend; missing `submitted` |
| `ClientOrder.status` | FIXED in PR #99 -- added new delivery statuses |

---

## Priority Implementation Plan

### Priority 1: Add ChainRecordTimeline to 5 Missing Pages (frontend)

One-line additions -- the component and backend API already exist:

- [ ] Add `<ChainRecordTimeline documentType="customer_invoice" documentId={invoice.id} />` to `CustomerInvoiceDetailPage.tsx`
- [ ] Add `<ChainRecordTimeline documentType="vendor_invoice" documentId={invoice.id} />` to `APInvoiceDetailPage.tsx`
- [ ] Add `<ChainRecordTimeline documentType="delivery_receipt" documentId={dr.id} />` to `DeliveryReceiptDetailPage.tsx`
- [ ] Add `<ChainRecordTimeline documentType="client_order" documentId={order.id} />` to sales `ClientOrderDetailPage.tsx`
- [ ] Add `<ChainRecordTimeline documentType="delivery_schedule" documentId={ds.id} />` to `DeliveryScheduleDetailPage.tsx`

### Priority 2: Replace Manual DR Creation with Workflow (frontend)

- [ ] Replace "Create Delivery Receipt" button on `DeliveryScheduleDetailPage.tsx` with "Dispatch" button that calls DS dispatch endpoint
- [ ] Show linked DR as read-only card when it already exists
- [ ] Remove manual shipment creation form from `ShipmentsPage.tsx` -- make it a tracking dashboard only

### Priority 3: Fix TypeScript Types (frontend)

- [ ] Add `delivered` to `PurchaseOrderStatus` type
- [ ] Add `delivered` to PO detail page `statusLabel` map  
- [ ] Fix `GoodsReceiptStatus`: add `submitted`, remove `rejected` and `partial_accept`

### Priority 4: Fix Sales Order Status Jump (backend)

- [ ] Change `UpdateSalesOrderOnProductionComplete` to transition to `ready_for_delivery` instead of `delivered`
- [ ] Wire delivery events to transition Sales Order to `delivered`

### Priority 5: Enforce DR Link on Product Invoices (backend + frontend)

- [ ] Add warning on `CustomerInvoiceFormPage.tsx` when `invoice_type = product` and no DR linked
- [ ] The backend validation already requires it via CHAIN-AR-001 -- just needs frontend alignment
