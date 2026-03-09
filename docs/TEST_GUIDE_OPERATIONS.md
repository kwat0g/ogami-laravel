# Ogami ERP — Operations Test Guide

> **Account:** `superadmin@ogamierp.local` / `SuperAdmin@12345!`
> **Environment:** `http://localhost:5173` (dev) or production URL
> **Single account throughout** — no login/logout required between steps.
> **Queue worker required:** `php artisan queue:work` — several modules auto-create records via background queue. Run this in a separate terminal before starting.

## Prerequisites (Scenario 0 already done)

The following master data is assumed to already exist:

| What | Details |
|------|---------|
| Bank Account | BDO — Account No. 0000-1234-5678 |
| Vendor | Chinatown Resins Inc. (TIN: 000-123-456-000, EWT Subject, ATC: WI010) |
| Customer | Ace Hardware Philippines (TIN: 000-987-654-000, Credit Limit ₱500,000) |
| Item 1 | PP Resin Natural — Raw Material, kg, IQC required |
| Item 2 | HDPE Resin Black — Raw Material, kg, IQC required |
| Item 3 | Plastic Container 500ml — Finished Good, pcs, IQC required |
| Warehouse | WH-A1 — Warehouse A – Rack 1 |
| BOM | Plastic Container 500ml v1.0 — PP Resin Natural 0.0192 kg/unit (4% scrap), HDPE Resin Black 0.0008 kg/unit (2% scrap) |

---

## Modules Covered

| # | Module | Key Workflow |
|---|--------|-------------|
| 1 | Procurement | PR → 4-step approval → PO → 2× GR with 3-way match |
| 2 | Inventory | IQC inspection + stock balance + ledger verification |
| 3 | Accounts Payable | 5-step invoice approval × 2 invoices → payment |
| 4 | Production | Delivery Schedule → WO → auto-MRQ 6-step chain → Output → Complete |
| 5 | Maintenance | Equipment records → Corrective WO → PM Schedule |
| 6 | Mold | Mold record → shot log → criticality badge → auto preventive WO |
| 7 | Delivery | Auto-DR confirm → Shipment → In Transit → Delivered |
| 8 | Accounts Receivable | Auto-invoice → Approve → Receive Payment |

---

## Real-Life Timeline

```
[March 15] PR raised for 500 kg PP Resin → approval chain → PO sent
[March 18] GR-1: 498 kg received → 3-way match → stock +498 kg → AP invoice auto-created
[March 18] IQC inspection on received PP Resin → passed
[March 21] GR-2: 2 kg received → fully received → second AP invoice auto-created
[March 22] AP invoices approved and paid (₱90,000 total)
[March 22] Equipment breakdown → corrective WO raised → resolved same day → PM Schedule added
[March 22] Mold record created → shot log (10,050 shots) → CRITICAL badge → auto preventive WO
[March 22] Production WO created for 10,000 units → Released → auto-MRQ raised
[March 22] Auto-MRQ approved through 6-step chain → Fulfilled → stock −~200 kg
[March 22] Production WO started → in_progress
[March 28] Production WO: output logged (10,050 produced, 43 rejected) → Completed
[March 28] Auto-DR confirmed → Shipment created → in_transit → delivered
[March 28] AR invoice auto-created on shipment delivered
[April 5]  AR invoice approved → customer payment received
```

---

## Scenario 1 — Procurement: PR → PO → Goods Receipt

> **Story:** The warehouse is low on PP Resin. A purchase request is raised for 500 kg, pushed through the full 4-step approval chain, converted to a PO, and goods are received in two separate deliveries.

### 1.1 Create a Purchase Request

1. Go to **Procurement → Purchase Requests → New Request**
2. Fill in:
   - **Department:** Production
   - **Urgency:** Normal
   - **Justification:** `Warehouse stock of PP Resin Natural is below safety level. Requesting 500 kg to cover the March production run.` *(min 20 characters)*
3. Under the line items section, click **Add Line** and fill in the row:
   - **Item Description:** `PP Resin Natural` *(free-text — PR does not use the item master dropdown)*
   - **Unit of Measure:** kg
   - **Quantity:** 500
   - **Estimated Unit Cost:** 180.00
4. Verify the **Grand Total** shows ₱90,000.00 (auto-computed)
5. Click **Submit**

✅ Status: `draft` → PR detail page loads; reference: `PR-2026-03-00001`

6. On the PR detail page, click **Submit PR**
7. A comments box appears — type `Stock below reorder level` (optional), then click **Confirm Submit**

✅ Status: `submitted`

---

### 1.2 Push the PR Through the 4-Step Approval Chain

All four steps are done with the same superadmin account. Each button opens a comment modal — enter optional comments then click the **Confirm** button to proceed.

> ⚠️ The label on the second click changes to "Confirm [Action]" — you must click twice per step: first to open the form, then to confirm.

**Step 1 — Head Note**
1. Click **Head Note PR**
2. Enter comment: `Stock below reorder level — approved for purchase` *(optional)*
3. Click **Confirm Note**

✅ Status: `noted`

**Step 2 — Check**
1. Click **Check PR**
2. Enter comment: `Chinatown Resins confirmed — lead time 3 days` *(optional)*
3. Click **Confirm Check**

✅ Status: `checked`

**Step 3 — Review**
1. Click **Review PR**
2. Enter comment: *(optional)*
3. Click **Confirm Review**

✅ Status: `reviewed`

**Step 4 — VP Approve**
1. Click **Approve PR**
2. Enter comment: *(optional)*
3. Click **Confirm Approve**

✅ Status: `approved`
✅ The **Create PO** button now appears on the PR detail page

> **To reject at any stage:** Click **Reject at [stage name]**, type a reason (min 10 characters), click **Confirm Reject**. The PR returns to draft for editing and re-submission.

---

### 1.3 Convert the Approved PR to a Purchase Order

1. On the `approved` PR detail page, click **Create PO** — the PO form opens pre-filled with the PR line items
2. Fill in the PO header:
   - **Vendor:** Chinatown Resins Inc.
   - **Delivery Date:** 2026-03-18
   - **Payment Terms:** Net 30
3. On the line item row:
   - **Item (Item Master):** search and select **PP Resin Natural** *(required — links the PO line to the item master for stock tracking)*
   - **Quantity Ordered:** 500
   - **UOM:** kg
   - **Agreed Unit Cost:** 180.00
4. Verify the **Grand Total** shows ₱90,000.00
5. Click **Create PO**

✅ Status: `draft`; reference: `PO-2026-03-00001`

6. On the PO detail page, click **Send to Vendor**
7. Confirm the action when prompted

✅ Status: `sent`
✅ PR status updates to: `converted_to_po`

> ⚠️ **The Item Master link is required on every PO line.** PRs use free-text descriptions; the PO is where each line is linked to an Item Master record. This drives stock updates when GRs are confirmed.

---

### 1.4 Record the First Goods Receipt (498 kg — short delivery)

> ⚠️ Make sure `php artisan queue:work` is running — stock updates and AP invoice auto-creation happen in the background queue.

1. On the `sent` PO detail page, click **Receive Goods**
2. Fill in the GR form:
   - **Received Date:** 2026-03-18
   - **Delivery Note Number:** *(leave blank)*
3. On the line item row:
   - **Quantity Received:** 498 *(change from 500 — the vendor shipped short)*
   - **Condition:** Good
4. Click **Record Receipt**

✅ Status: `draft`; reference: `GR-2026-03-00001`

5. Click **Confirm Receipt & Run 3-Way Match**

✅ Toast: *"GR confirmed. Three-way match PASSED"*
✅ GR status: `confirmed`
✅ PO status: `partially_received` *(2 kg still outstanding)*
✅ **Inventory → Stock Balances:** PP Resin Natural = 498 kg *(requires queue worker)*
✅ **Accounting → AP Invoices:** draft AP invoice for Chinatown Resins Inc. auto-created (₱89,640) *(requires queue worker)*

---

### 1.5 Record the Second Goods Receipt (remaining 2 kg)

1. On the `partially_received` PO detail page, click **Receive Goods**
2. Fill in the GR form:
   - **Received Date:** 2026-03-21
   - **Quantity Received:** 2 *(pre-filled from outstanding balance — confirm it)*
   - **Condition:** Good
3. Click **Record Receipt** → on the GR detail page, click **Confirm Receipt & Run 3-Way Match**

✅ GR status: `confirmed`; reference: `GR-2026-03-00002`
✅ PO status: `fully_received`
✅ **Inventory → Stock Balances:** PP Resin Natural total = 500 kg
✅ **Accounting → AP Invoices:** second draft AP invoice auto-created (₱360)

> **Two draft AP invoices now exist in Accounting → AP Invoices:**
> - GR-2026-03-00001 → ₱89,640 (498 kg × ₱180)
> - GR-2026-03-00002 → ₱360 (2 kg × ₱180)

---

## Scenario 2 — Inventory: Stock Verification

> **Story:** Verify the stock increase from both GRs, review the stock ledger for movement history, and optionally adjust if anything is off.

### 2.1 Verify Stock Balance

1. Go to **Inventory → Stock Balances**
2. Use the **Location** dropdown and select **Warehouse A – Rack 1 (WH-A1)**
3. Find **PP Resin Natural** in the table
4. ✅ On Hand = 500 kg (498 from GR-1 + 2 from GR-2)
5. Check the **Reorder Pt.** column — reorder point is 500 kg
6. A **Low** badge may appear since on-hand equals the reorder threshold — this is expected

> **Low stock badge:** Appears whenever `On Hand ≤ Reorder Point`. Use the **Low stock only** checkbox to filter only low-stock items.

---

### 2.2 Verify the Stock Ledger

1. Go to **Inventory → Stock Ledger**
2. Set filters:
   - **Transaction Type:** Goods Receipt
   - **Date From:** 2026-03-18
   - **Date To:** 2026-03-21
3. ✅ Two inbound entries visible:
   - GR-2026-03-00001: **+498 kg** · 2026-03-18 · WH-A1
   - GR-2026-03-00002: **+2 kg** · 2026-03-21 · WH-A1
4. ✅ **Balance After** column shows the cumulative running total
5. Click the **Reference** link on any row to navigate directly to that GR record

---

### 2.3 Adjust Stock If Needed (Physical Count Does Not Match)

> Only needed if the physical count differs from system values. Skip if balances are correct.

1. In **Inventory → Stock Balances**, find the item that needs correction
2. Click the **Adjust** button on that row
3. An inline form appears:
   - **New Quantity:** enter the correct physical count *(the actual number — not the difference)*
   - **Remarks:** required, min 10 characters (e.g., `Physical count confirmed 500 kg on rack — 5 damaged bags set aside`)
4. Click **Adjust**

✅ The **On Hand** column updates immediately
✅ Stock Ledger shows a new **Adjustment** entry

---

## Scenario 3 — Accounts Payable: Invoice Approval and Payment

> **Story:** Two AP invoices for Chinatown Resins Inc. were auto-created when the GRs were confirmed. Push both through the full 5-step approval chain and record payment.

### 3.1 Locate the Auto-Created AP Invoices

1. Go to **Accounting → AP Invoices**
2. ✅ Two draft invoices for **Chinatown Resins Inc.**:
   - Invoice 1: Net Amount **₱89,640.00** (498 × ₱180 — from GR-2026-03-00001)
   - Invoice 2: Net Amount **₱360.00** (2 × ₱180 — from GR-2026-03-00002)
3. Both show status `draft`; description references the GR number

> **If invoices are missing** (queue worker was not running): Go to **Accounting → AP Invoices → New Invoice**. Fill in: **Vendor:** Chinatown Resins Inc. · **Fiscal Period:** March 2026 · **AP Account:** 2001 · **Expense Account:** 6001 · **Invoice Date:** 2026-03-18 · **Due Date:** 2026-04-17 · **Net Amount:** 89,640.00 (first) or 360.00 (second) → Click **Save**.

---

### 3.2 Push Invoice 1 (₱89,640) Through the 5-Step Approval Chain

Open the ₱89,640 invoice detail page and click each button in order:

| Step | Button | Click Confirm? | Status After |
|------|--------|----------------|--------------|
| 1 | **Submit for Approval** | No extra confirm | `pending_approval` |
| 2 | **Head Note** | Yes — **Confirm Note** | `head_noted` |
| 3 | **Manager Check** | Yes — **Confirm Manager Check** | `manager_checked` |
| 4 | **Officer Review** | Yes — **Confirm Officer Review** | `officer_reviewed` |
| 5 | **Approve** | Yes — **Confirm Approve** | `approved` |

✅ After **Approve**: GL journal entry auto-posted:
- DR Materials Expense (6001) ₱89,640
- CR Accounts Payable (2001) ₱89,640

✅ **With EWT (ATC WI010 configured):** EWT ₱1,792.80 (2%) auto-computed; visible on detail page under **EWT Amount**
✅ **Without EWT:** EWT = ₱0; Net Payable = ₱89,640

---

### 3.3 Record Payment for Invoice 1

1. On the `approved` ₱89,640 invoice, scroll to **Record Payment** section
2. Fill in:
   - **Payment Amount:**
     - *With EWT:* `87,847.20` *(₱89,640 − ₱1,792.80 EWT)*
     - *Without EWT:* `89,640.00`
   - **Payment Date:** 2026-03-25
   - **Payment Method:** Bank Transfer
   - **Bank Account:** BDO *(select from dropdown)*
   - **Reference Number:** `BDO-TRF-20260325-001` *(optional)*
3. Click **Record Payment**

✅ Invoice status: `paid` · Balance Due: ₱0
✅ GL entry posted **with EWT:**
- DR Accounts Payable (2001) ₱89,640
- CR EWT Payable ₱1,792.80
- CR Cash / Bank (1001) ₱87,847.20

---

### 3.4 Push Invoice 2 (₱360) Through the 5-Step Approval Chain

Repeat the same 5 steps as 3.2:

| Step | Button | Status After |
|------|--------|--------------|
| 1 | **Submit for Approval** | `pending_approval` |
| 2 | **Head Note** → **Confirm Note** | `head_noted` |
| 3 | **Manager Check** → **Confirm** | `manager_checked` |
| 4 | **Officer Review** → **Confirm** | `officer_reviewed` |
| 5 | **Approve** → **Confirm** | `approved` |

✅ GL entry auto-posted: DR Expense (6001) ₱360 / CR AP (2001) ₱360

---

### 3.5 Record Payment for Invoice 2

1. On the `approved` ₱360 invoice, go to **Record Payment**
2. Fill in:
   - **Payment Amount:**
     - *With EWT:* `352.80` *(₱360 − ₱7.20 EWT)*
     - *Without EWT:* `360.00`
   - **Payment Date:** 2026-03-25
   - **Bank Account:** BDO
3. Click **Record Payment**

✅ Invoice status: `paid`

---

### 3.6 Check the AP Due Date Monitor

1. Go to **Accounting → AP Due Date Monitor**
2. ✅ Neither invoice appears under **Overdue** or **Due Soon** — both are `paid`
3. Overview columns: **Overdue** · **Due Soon** · **Pending Approval**

---

## Scenario 4 — Production: Delivery Schedule → Work Order → Auto-MRQ → Output Log

> **Story:** Production runs 10,000 units of Plastic Container 500ml for Ace Hardware. A Delivery Schedule is created first, the WO is released (auto-creating a Material Requisition), the MRQ is fully approved and fulfilled to issue stock, then the WO is started, output logged, and completed.

### 4.1 Create a Delivery Schedule

1. Go to **Production → Delivery Schedules → New Schedule**
2. Fill in:
   - **Customer:** Ace Hardware Philippines *(dropdown)*
   - **Product Item:** Plastic Container 500ml *(finished goods only)*
   - **Qty Ordered:** 10,000
   - **Unit Price (₱):** 28.00 *(used to compute the AR invoice subtotal — do not leave blank)*
   - **Target Delivery Date:** 2026-03-28
   - **Type:** Local
3. Click **Create Schedule**

✅ Status: `open`

---

### 4.2 Create the Production Work Order

1. Go to **Production → Work Orders → New Order**
2. Fill in:
   - **Product Item:** Plastic Container 500ml *(select first — BOM dropdown filters to this product)*
   - **Bill of Materials:** *(auto-selects the active v1.0 BOM — confirm it is selected)*
   - **Delivery Schedule:** *(select the schedule from step 4.1)*
   - **Qty Required:** 10,000
   - **Target Start Date:** 2026-03-22
   - **Target End Date:** 2026-03-25
3. Review the BOM components shown at the bottom:
   - PP Resin Natural: 0.0192 kg/unit, 4% scrap
   - HDPE Resin Black: 0.0008 kg/unit, 2% scrap
4. Click **Create Work Order**

✅ Status: `draft`; reference: `WO-2026-03-00001`

---

### 4.3 Release the Work Order

1. On the `draft` WO detail page, click **Release**

✅ Status: `released`
✅ An **auto-generated Material Requisition** is created in `draft` state (e.g., `MRQ-2026-03-00001`)

> ⚠️ **The auto-MRQ must be fully approved and fulfilled before starting the WO.** Clicking **Start Production** while any linked MRQ is not `fulfilled` returns a `PROD_MRQ_NOT_FULFILLED` error.

---

### 4.3.5 Add HDPE Resin Black Stock (Required Before MRQ Fulfillment)

> **This step is mandatory.** Only PP Resin Natural was procured in Scenario 1. The BOM also requires HDPE Resin Black, and the MRQ fulfill step pre-checks **all** line items at once — a zero balance on HDPE Resin Black will block fulfillment with an `INV_INSUFFICIENT_STOCK` error before any stock moves.

**How much is needed:** 10,000 × 0.0008 kg/unit × 1.02 (2% scrap) = **8.16 kg**. Add 20 kg to leave a comfortable surplus.

1. Go to **Inventory → Stock Balances**
2. Locate **HDPE Resin Black** in the list (it will show 0 kg or may not appear if no transactions have ever occurred)
   - If the item is not visible, use the **Item** search/filter at the top of the table to find it
3. Click the **Adjust** button on the HDPE Resin Black row
4. Fill in the adjustment form:
   - **New Quantity:** `20` *(the actual quantity — not the difference; 20 kg is well above the 8.16 kg the MRQ will request)*
   - **Remarks:** `Initial stock load for March production run`
5. Click **Adjust**

✅ HDPE Resin Black: **20 kg** at WH-A1
✅ Stock Ledger shows a new **Adjustment** entry for HDPE Resin Black

---

### 4.4 Push the Auto-MRQ Through the Full 6-Step Approval Chain

1. Go to **Inventory → Requisitions** — find the new MRQ (status `draft`, Purpose references `WO-2026-03-00001`)
2. Open the MRQ detail page
3. Click through all six steps:

| Step | Button | Click Confirm? | Status After |
|------|--------|----------------|--------------|
| 1 | **Submit** | No | `submitted` |
| 2 | **Note** | Yes — **Confirm Note** | `noted` |
| 3 | **Check** | Yes — **Confirm Check** | `checked` |
| 4 | **Review** | Yes — **Confirm Review** | `reviewed` |
| 5 | **VP Approve** | Yes — **Confirm Approve** | `approved` |
| 6 | **Fulfill (Issue Stock)** | See below | `fulfilled` |

**Step 6 — Fulfill:**
- Click **Fulfill (Issue Stock)** — the **Issue From Location** dropdown appears
- Select **Warehouse A – Rack 1 (WH-A1)**
- Click **Confirm Fulfill**

✅ MRQ status: `fulfilled`
✅ **Inventory → Stock Balances:** PP Resin Natural decreases by ~200 kg (10,000 × 0.0192 kg + 4% scrap)
✅ Stock Ledger shows a new **Issue** entry for `MRQ-2026-03-00001`

> ⚠️ **Steps 2–5 require two clicks each.** First click reveals a comments textarea and changes the button label to "Confirm [Action]". Comments are optional — click the Confirm button to proceed.

---

### 4.5 Start the Work Order

1. Go to **Production → Work Orders**, open `WO-2026-03-00001` (status `released`)
2. Click **Start Production**

✅ Status: `in_progress`

---

### 4.6 Log Production Output

1. On the `in_progress` WO detail page, click **Log Output**
2. Fill in the inline form:
   - **Shift:** Shift A
   - **Date:** 2026-03-28
   - **Operator:** *(select any employee)*
   - **Qty Produced:** 10,050
   - **Qty Rejected:** 43
   - **Remarks:** `March production run — FGD-001` *(optional)*
3. Click **Submit Log**

✅ New row in **Output Logs** table
✅ Progress bar updates: 10,050 / 10,000 = 100.5%

---

### 4.7 Mark the Work Order Complete

1. On the `in_progress` WO detail page, click **Mark Complete**
2. Confirm the dialog

✅ Status: `completed`
✅ **Inventory → Stock Balances:** Plastic Container 500ml increases by **10,007 pcs** (10,050 − 43 rejected)
✅ Stock Ledger shows a **Production Output** entry
✅ Delivery Schedule status updates to `ready`
✅ A **Draft Outbound Delivery Receipt** is auto-created for Ace Hardware Philippines *(requires queue worker)*

---

## Scenario 5 — Maintenance: Equipment → Corrective Work Order → PM Schedule

> **Story:** Injection Moulding Machine #1 breaks down during the production run. Two equipment records are created, the corrective WO is raised and resolved, and a PM schedule is added.

### 5.1 Add Equipment Records

**Equipment 1 — Injection Moulding Machine #1:**
1. Go to **Maintenance → Equipment → Add Equipment** (+ icon)
2. Fill in:
   - **Name:** Injection Moulding Machine #1
   - **Category:** Production
   - **Status:** Operational
   - **Manufacturer:** Engel
   - **Model No.:** ES200/50
   - **Serial No.:** EM-2018-00123
   - **Location:** Production Floor A
   - **Date Commissioned:** 2018-06-01
3. Click **Save**

✅ Status badge: **Operational**

**Equipment 2 — Hydraulic Press #3:**
1. Click **Add Equipment** again
2. Fill in: **Name:** Hydraulic Press #3 · **Category:** Production · **Manufacturer:** Schuler · **Model No.:** HP-320 · **Serial No.:** HP-2015-00789 · **Location:** Production Floor B · **Date Commissioned:** 2015-03-20
3. Click **Save**

✅ Both equipment records visible in **Maintenance → Equipment**

---

### 5.2 Raise a Corrective Work Order

1. Go to **Maintenance → Work Orders → New Work Order**
2. Fill in:
   - **Equipment:** Injection Moulding Machine #1 *(dropdown)*
   - **Type:** Corrective
   - **Priority:** Critical
   - **Title:** Hydraulic system leak — production stoppage March 22
   - **Description:** Machine stopped. Hydraulic fluid leaking from main cylinder seal. Requires seal replacement. *(optional)*
   - **Assigned To:** *(select any employee, or leave unassigned)*
   - **Scheduled Date:** 2026-03-22
3. Click **Create Work Order**

✅ Status: `open`; reference: `WO-MAINT-2026-00001`

4. Click **Start Work**
5. A confirmation dialog appears: *"Start this work order? Make sure a technician is assigned before starting."* — click **OK**

✅ Status: `in_progress`

---

### 5.3 Complete the Work Order

1. On the `in_progress` WO detail page, click **Complete**
2. Fill in the inline form:
   - **Actual Completion Date:** 2026-03-22
   - **Labor Hours:** 3.5
   - **Completion Notes:** `Replaced main cylinder seal (Part No: MC-SEAL-017). Pressure test passed at 250 bar. Machine operational.` *(required)*
3. Click **Mark as Completed**

✅ Status: `completed`

---

### 5.4 Add a Preventive Maintenance Schedule

1. Go to **Maintenance → Equipment** → open **Injection Moulding Machine #1**
2. Scroll to the **PM Schedules** section → click **+ Add PM Schedule**
3. Fill in:
   - **Task Name:** Monthly Lubrication Service
   - **Frequency (days):** 30
   - **Last Done On:** 2026-03-22
4. Click **Save**

✅ New row in PM Schedules table:
- Task: Monthly Lubrication Service · Frequency: 30d · Last Done: 2026-03-22 · **Next Due: 2026-04-21**

---

## Scenario 6 — Mold: Shot Log and Critical Monitor

> **Story:** Create the mold used in production. Log 10,050 shots from the March run. Because this exceeds the max_shots ceiling of 10,000, the mold goes CRITICAL and a preventive WO is auto-created.

### 6.1 Create the Mold Record

1. Go to **Mold → New Mold** (+ icon)
2. Fill in:
   - **Name:** Container 500ml – Cavity 4
   - **Description:** 4-cavity mould for 500ml PP container *(optional)*
   - **Cavity Count:** 4
   - **Max Shots:** 10,000 *(intentionally below the production run to trigger CRITICAL)*
   - **Material:** P20 Tool Steel *(optional)*
   - **Location:** Mold Room Rack A *(optional)*
   - **Status:** Active
3. Click **Create Mold**

✅ Detail page loads; `current_shots` = 0; progress bar 0 / 10,000

---

### 6.2 Check Baseline Status

1. Go to **Mold** (main list) → locate **Container 500ml – Cavity 4**
2. ✅ `current_shots` = 0, `max_shots` = 10,000, progress bar at 0%

---

### 6.3 Log Production Shots

1. On the mold detail page, click **+ Log Shots**
2. Fill in:
   - **Shot Count:** 10,050
   - **Log Date:** 2026-03-22
   - **Remarks:** `March production run FGD-001` *(optional)*
   - **Operator:** *(optional)*
   - **Production Order:** *(optional — select WO-2026-03-00001 if available)*
3. Click **Save**

✅ Shot Log table shows the new row
✅ **Total Shots** updates to 10,050 (exceeds max of 10,000)
✅ Progress bar fills past 100% and turns red/warning
✅ **CRITICAL** badge appears next to the mold name
✅ **Maintenance → Work Orders:** a new **Preventive** WO linked to this mold appears *(requires queue worker)*

---

## Scenario 7 — Delivery: Outbound Shipment

> **Story:** The 10,007 finished units are ready for dispatch. A draft Delivery Receipt was auto-created when the production order completed. Confirm the DR, create a shipment, and track it to delivery.

### 7.1 Confirm the Auto-Created Delivery Receipt

1. Go to **Delivery → Delivery Receipts**
2. ✅ A **Draft** Outbound DR for **Ace Hardware Philippines** is listed *(auto-created in Scenario 4.7)*
3. Verify: Direction — Outbound · Customer — Ace Hardware Philippines · Line: Plastic Container 500ml, Qty 10,000
4. Open the DR → click **Confirm Receipt**
5. Confirm the dialog: *"Confirm delivery receipt? This action cannot be undone."* → click **Confirm**

✅ DR status: `confirmed`; reference: `DR-2026-03-00001`
✅ **Inventory → Stock Balances:** Plastic Container 500ml decreases by 10,000

> **If no auto-DR exists:** Go to **Delivery → Delivery Receipts → New Receipt** · Direction: Outbound (Dispatch) · Customer: Ace Hardware Philippines · Receipt Date: 2026-03-28 · Add line: Item: Plastic Container 500ml · Expected Qty: 10,000 · Received Qty: 10,000 · UoM: pcs → **Create Receipt** → **Confirm Receipt**

---

### 7.2 Create a Shipment

1. Go to **Delivery → Shipments → New Shipment** (+ icon)
2. Fill in:
   - **Delivery Receipt:** DR-2026-03-00001 — Ace Hardware Philippines *(confirmed DRs only)*
   - **Carrier:** JRS Express
   - **Tracking No.:** JRS-2026-032801
   - **Shipped Date:** 2026-03-28
   - **Estimated Arrival:** 2026-03-29
3. Click **Create Shipment**

✅ Status: `pending`

---

### 7.3 Track the Shipment to Delivery

**Mark In Transit:**
1. On the Shipments list, find the `pending` shipment
2. Click the action/chevron button on the right side of the row to expand it
3. Click **Confirm: Mark In Transit**

✅ Status: `in_transit`

**Mark Delivered:**
1. Click the action button again — an **Actual Arrival Date** field appears
2. Enter `2026-03-29`
3. Click **Confirm: Mark Delivered**

✅ Status: `delivered`
✅ Delivery Schedule status updates to `delivered`
✅ A **Draft Customer Invoice** for Ace Hardware Philippines is auto-created *(requires queue worker)*
// QUEUE WORK DIDN'T WORK
---

## Scenario 8 — Accounts Receivable: Invoice Approval and Collection

> **Story:** Bill Ace Hardware Philippines for 10,000 units at ₱28.00 each. The AR invoice was auto-created when the shipment was marked delivered. Approve it and record the customer payment.

### 8.1 Locate the Auto-Created AR Invoice

1. Go to **Accounting → AR Invoices**
2. ✅ A draft invoice for **Ace Hardware Philippines** is visible *(auto-created in Scenario 7.3)*
3. Verify:
   - **Subtotal:** ₱280,000.00 *(10,000 × ₱28.00 from the delivery schedule)*
   - **VAT Amount:** ₱33,600.00 *(12% of ₱280,000)*
   - **Total:** ₱313,600.00

> **If the invoice is missing** (queue worker not running): Go to **Accounting → AR Invoices → New Invoice**. Fill in: Customer: Ace Hardware Philippines · Fiscal Period: March 2026 · AR Account: Accounts Receivable · Revenue Account: Sales Revenue · Invoice Date: 2026-03-28 · Due Date: 2026-04-27 · Subtotal: 280,000.00 · VAT Amount: 33,600.00 → Click **Create Invoice (Draft)**.

> **If the auto-invoice shows ₱0 subtotal:** The delivery schedule was created without a Unit Price. Edit the draft invoice — set **Subtotal** to `280000.00` and **VAT Amount** to `33600.00` — then save before approving.

---

### 8.2 Approve the Invoice

1. On the invoice list, click **Approve** on the draft row (or open the detail page and click **Approve Invoice**)
2. A confirmation dialog appears — type **APPROVE** (all caps) and click OK

✅ Status: `approved`
✅ Invoice Number assigned (e.g., `INV-2026-03-000001`)
✅ GL entry auto-posted:
- DR Accounts Receivable (3001) ₱313,600
- CR Sales Revenue (4001) ₱280,000
- CR Output VAT Payable ₱33,600

---

### 8.3 Receive Customer Payment

1. Open the `approved` AR invoice detail page
2. Click **Receive Payment** — a form appears below
3. Fill in:
   - **Amount (₱):** 313,600.00 *(defaults to full balance — leave as is)*
   - **Payment Date:** 2026-04-05
   - **Cash Account ID:** BDO *(select from dropdown)*
   - **AR Account ID:** Accounts Receivable *(same as on the invoice)*
   - **Reference #:** `BDO-TRF-20260405-001` *(optional)*
   - **Payment Method:** Bank Transfer
4. Click **Record**

✅ Status: `paid` · Balance Due: ₱0
✅ **Payment History** section shows the recorded payment
✅ GL entry auto-posted:
- DR Cash / Bank (1001) ₱313,600
- CR Accounts Receivable (3001) ₱313,600

> ⚠️ If you enter more than the balance due, the warning *"₱[excess] → advance payment (AR-005)"* appears. Make sure the amount is exactly ₱313,600.00.

---

## End-to-End Sanity Checklist

After completing all 8 scenarios, verify these summary points:

| Check | Expected |
|-------|----------|
| PP Resin Natural stock | ~300 kg (500 received − ~200 issued via MRQ) |
| HDPE Resin Black stock | ~11.84 kg (20 added via adjustment − 8.16 issued via MRQ) |
| Plastic Container 500ml stock | 7 pcs (10,007 produced − 10,000 delivered) |
| AP Invoice 1 (₱89,640) | Status `paid` · Balance Due ₱0 |
| AP Invoice 2 (₱360) | Status `paid` · Balance Due ₱0 |
| AR Invoice (₱313,600) | Status `paid` · Balance Due ₱0 |
| Maintenance WO (IMM#1) | Status `completed` |
| PM Schedule (IMM#1) | Monthly Lubrication Service · Next Due 2026-04-21 |
| Mold Container 500ml – Cavity 4 | `current_shots` = 10,050; **CRITICAL** badge; auto preventive WO created |
| Delivery Receipt | Status `confirmed` |
| Shipment | Status `delivered` |
| Delivery Schedule | Status `delivered` |
| Stock Ledger | GR (×2), Issue (MRQ), Production Output, Issue (DR) — all visible |

---

## Auto-Trigger Verification

These integrations fire automatically via queued events.

| Trigger | Where to Verify | Scenario |
|---------|----------------|---------|
| GR Confirmed → Stock increase | Inventory → Stock Balances (PP Resin +498 kg, then +2 kg) | 1.4, 1.5 |
| GR Confirmed → Draft AP Invoice | Accounting → AP Invoices (one draft per GR) | 1.4, 1.5 |
| AP Invoice Approved → GL posted | GL — DR Expense / CR AP | 3.2, 3.4 |
| AP Payment Recorded → GL posted | GL — DR AP / CR EWT + Cash | 3.3, 3.5 |
| WO Released → Draft MRQ | Inventory → Requisitions (MRQ in draft, WO reference in Purpose) | 4.3 |
| WO Completed → FG stock increase | Inventory → Stock Balances (Plastic Container 500ml +10,007) | 4.7 |
| WO Completed → Draft outbound DR | Delivery → Delivery Receipts (draft DR for Ace Hardware) | 4.7 |
| Mold shots ≥ max → CRITICAL badge | Mold detail page (CRITICAL badge, filled progress bar) | 6.3 |
| Mold shots ≥ max → Preventive WO | Maintenance → Work Orders (new Preventive WO linked to mold) | 6.3 |
| Shipment Delivered → Draft AR Invoice | Accounting → AR Invoices (draft for Ace Hardware Philippines) | 7.3 |
| AR Invoice Approved → GL posted | GL — DR AR / CR Revenue / CR Output VAT | 8.2 |
| AR Payment Received → GL posted | GL — DR Cash / CR AR | 8.3 |

---

## Common Issues and Quick Fixes

| Symptom | Fix |
|---------|-----|
| PR line items have no item master dropdown | Correct — PR uses free-text **Item Description**; item master link is done on the PO |
| Create PO button missing on approved PR | Button appears only after **VP Approve** — confirm status is `approved` not `reviewed` |
| Receive Goods button missing on PO | PO must be in `sent` or `partially_received`; hidden for `draft`, `fully_received`, `cancelled` |
| AP invoice not auto-created after GR confirm | Verify queue worker is running; or create manually (see step 3.1 fallback) |
| AP invoice 409 on Head Note | Must click **Submit for Approval** first — invoice must reach `pending_approval` before any other step |
| AP approval steps require two clicks | First click opens the comments form — enter optional text, then click **Confirm [Action]** |
| MRQ not visible after WO Released | Go to **Inventory → Requisitions** and refresh — may take a few seconds |
| MRQ Fulfill has no location dropdown | Dropdown appears after clicking **Fulfill (Issue Stock)** — select *WH-A1* then click **Confirm Fulfill** |
| Start Production returns `PROD_MRQ_NOT_FULFILLED` | Complete the full 6-step MRQ chain (step 4.4) — all linked MRQs must reach `fulfilled` |
| Log Output saves but progress bar stays 0% | Refresh the page — progress bar reads from the database and may need a reload |
| Mark Complete not visible on WO | WO must be `in_progress` AND have at least one Output Log with Qty Produced > 0 |
| Auto-DR not created after WO completed | Verify queue worker is running AND WO had a linked Delivery Schedule at creation |
| Auto-AR invoice has ₱0 subtotal | Delivery Schedule was created without a **Unit Price** — edit the draft invoice subtotal manually |
| Auto-AR invoice not found | Shipment must reach `delivered`; verify queue worker is running |
| Mold preventive WO not auto-created | Confirm `current_shots ≥ max_shots`; verify queue worker is running |
| Confirm: Mark In Transit/Delivered not visible | Click the chevron / action button on the right side of the shipment row to expand it |
| AR Invoice Approve dialog not accepting APPROVE | Type exactly `APPROVE` in all caps — autocorrect may modify it |
| AR payment warning about advance | Amount entered exceeds balance due — change to exactly ₱313,600.00 |
