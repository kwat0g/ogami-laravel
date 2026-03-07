# Ogami ERP — Core Modules Testing Guide (Superadmin)

> **Account:** `superadmin@ogamierp.local` / `SuperAdmin@12345!`
> **Environment:** `http://localhost:5173`
> **Before starting:** `php artisan migrate:fresh --seed` then `npm run dev`
> **Single account throughout** — no login/logout required between steps.

This guide covers all core operational and financial modules in one connected real-life manufacturing cycle:

**Procurement → Inventory → AP (Pay Vendor) → Production → Maintenance → Mold → QC → Delivery → AR (Bill Customer) → Accounting → ISO**

Run the scenarios **in order** — each one produces data used by the next.

### Real-Life Timeline

```
[March 15] PR raised → approval chain → PO sent to vendor
[March 18] Goods received (GR-1: 498 kg) → stock updated → AP invoice auto-created
[March 18] IQC inspection on incoming RAW-001 → passed
[March 21] Remaining goods received (GR-2: 2 kg) → fully received
[March 22] Materials issued via MRQ (200 kg) → production started
[March 22] Equipment breakdown → corrective maintenance WO → resolved
[March 22] Mold shot log recorded after production run
[March 23] IPQC inspection → wall thickness below spec → NCR + CAPA
[March 25] AP invoices approved and paid; CAPA resolved; NCR closed
[March 25] OQC outgoing inspection → passed
[March 28] Production completed → 10,007 units FGD-001 in stock
[March 28] Delivery confirmed; AR invoice raised and approved
[March 29] Shipment delivered
[April 5]  Customer payment received
[March 31] Month-end JEs, trial balance, financial reports; ISO audit
```

---

## Seeded Master Data Reference

| Type | Code / Name | Notes |
|------|-------------|-------|
| Raw Material | `RAW-001` — PP Resin Natural | Primary production input |
| Raw Material | `RAW-002` — HDPE Resin Black | Secondary resin |
| Finished Good | `FGD-001` — Plastic Container 500ml | Saleable item |
| Vendor | Chinatown Resins Inc. | Pre-accredited, Net-30 terms |
| Customer | Ace Hardware Philippines | Active customer |
| Equipment | Injection Moulding Machine #1 | Active |
| Equipment | Hydraulic Press #3 | Active |
| Mold | Container 500ml – Cavity 4 | **CRITICAL** — 91% shot life used |
| Vehicle | TRUCK-001 — Delivery Truck 1 | Active |
| Vehicle | VAN-001 — Mitsubishi L300 Van | Active |

---

## Scenario 1 — Procurement: Full PR → PO → GR Cycle

> **Story:** The warehouse is low on PP Resin. A purchase request is raised for 500 kg, pushed through the full approval chain, converted to a PO, and goods are received in two deliveries (498 kg + 2 kg).

> **Queue prerequisite:** Inventory stock updates and AP invoice auto-creation are processed by a queue worker. Make sure `npm run dev` (or `php artisan queue:work`) is running before confirming GRs.

### 1.1 Create a Purchase Request

1. Go to **Procurement → Purchase Requests → New**
2. Fill in:
   - **Department:** Production
   - **Urgency:** Normal
   - **Justification:** Warehouse stock of PP Resin Natural is below safety level. Requesting 500 kg to cover the March production run.
3. Add a line item — click **+ Add Item**:
   - **Description:** `PP Resin Natural` *(free text — PRs have no item dropdown)*
   - **UoM:** kg
   - **Qty:** 500 · **Unit Cost:** ₱180.00 → **Est. Total:** ₱90,000.00
4. Click **Save**, then on the detail page click **Submit**
5. ✅ Status: `submitted` · Reference: `PR-2026-03-00001`

### 1.2 Push Through the Approval Chain

| Step | Button | Remark | Final Status |
|------|--------|--------|--------------|
| 1 — Head | **Note** | *Stock below reorder level* | `noted` |
| 2 — Manager | **Check** | *Approved for purchase* | `checked` |
| 3 — Officer | **Review** | *Chinatown Resins confirmed, lead time 3 days* | `reviewed` |
| 4 — VP | **VP Approve** | *(none required)* | `approved` |

✅ The **Create PO** button appears on the PR detail page.

### 1.3 Convert to Purchase Order

1. On the approved PR detail page, click **Create PO**
2. The form opens pre-filled — PO is locked to this PR; line items are copied from the PR
3. Fill in:
   - **Vendor:** Chinatown Resins Inc.
   - **Delivery Date:** 2026-03-18
   - **Payment Terms:** Net-30
   - **Unit Price:** ₱180.00/kg (confirm if needed)
4. Click **Create PO**, then on the PO detail page click **Send to Vendor**
5. ✅ Status: `sent` · Reference: `PO-2026-03-00001`
6. ✅ PR status is now `converted_to_po`

### 1.4 Record First Goods Receipt (498 kg — short delivery)

1. On the PO detail page, click **Receive Goods**
2. In the GR form:
   - **Received Date:** 2026-03-18
   - **Qty Received:** change `500` → `498`
   - All other fields: leave as default
3. Click **Record Goods Receipt**, then on the GR detail page click **Confirm Receipt & Run 3-Way Match**
4. ✅ Status: `confirmed` · Reference: `GR-2026-03-00001`
5. ✅ PO status: `partially_received`
6. ✅ **Inventory → Stock Balances:** RAW-001 increases by 498 kg *(requires queue worker)*
7. ✅ **Accounting → AP Invoices:** a draft AP invoice for Chinatown Resins is auto-created *(requires queue worker)*

### 1.5 Record Second Goods Receipt (remaining 2 kg)

1. On the PO detail page (status `partially_received`), click **Receive Goods**
2. In the GR form:
   - **Received Date:** 2026-03-21
   - **Qty Received:** `2` *(pre-filled from outstanding balance)*
3. Click **Record Goods Receipt** → **Confirm Receipt & Run 3-Way Match**
4. ✅ PO status: `fully_received`
5. ✅ **Inventory → Stock Balances:** RAW-001 total = 500 kg
6. ✅ A second draft AP invoice for the remaining 2 kg (₱360) is auto-created

> **Two draft AP invoices now exist in Accounting → AP Invoices:**
> - GR-2026-03-00001 → ₱89,640 (498 kg × ₱180)
> - GR-2026-03-00002 → ₱360 (2 kg × ₱180)
>
> Continue to **Scenario 2** (Inventory), then **Scenario 3** (AP) to approve and pay both invoices.

---

## Scenario 2 — Inventory: IQC Inspection, Stock Verification, and Material Issuance

> **Story:** On the same day the goods arrive (March 18), QC performs an incoming inspection on RAW-001. The warehouse then verifies on-hand balances and issues 200 kg to Production via material requisition.

### 2.1 Incoming Material Inspection (IQC)

> IQC is performed at the warehouse on the day goods are received — before materials are issued to Production.

1. Go to **QC / QA → Inspections → New**
2. Fill in:
   - **Stage:** IQC *(dropdown: iqc / ipqc / oqc)*
   - **Inspection Date:** 2026-03-18
   - **Item:** *(dropdown — search and select RAW-001 PP Resin Natural)*
   - **Qty Inspected:** 50
   - **Template:** *(optional — select PP Resin IQC template if seeded)*
   - **Inspector:** *(optional dropdown — select Superadmin or leave blank)*
   - **Remarks:** *(optional)*
3. Click **Create Inspection** → on the detail page, add results and click **Submit Results**
4. Set all criteria as **Conforming**:
   - Visual contamination: pass
   - Moisture content: pass, Actual `0.03%`
   - Melt Flow Index: pass, Actual `13.2`
5. ✅ Inspection status: `passed`; reference `INS-2026-IQC-001`

### 2.2 Verify Stock Balance

1. Go to **Inventory → Stock Balances**
2. Search for **RAW-001**
3. ✅ On-hand qty ≈ 500 kg (from Scenario 1)

### 2.3 Check Stock Ledger

1. Go to **Inventory → Stock Ledger**
2. Filter by **Item:** RAW-001
3. ✅ Two inbound transactions visible — GR-2026-03-00001 (498 kg) and GR-2026-03-00002 (2 kg)
4. ✅ Running balance column reflects cumulative totals correctly

### 2.4 Create a Material Requisition

1. Go to **Inventory → Requisitions → New**
2. Fill in:
   - **Department:** Production
   - **Purpose:** Materials for March production run for FGD-001 *(min 10 characters)*
3. In the **Line Items** section, search and select items from the **item dropdown** (existing inventory items):
   - **Item:** search for `PP Resin Natural` → select **RAW-001**
   - **Qty:** 200
   - **Remarks:** *(optional)*
4. Click **Submit**
5. ✅ MRQ reference generated; status `submitted`

> **Note:** Unlike PRs, MRQ line items use a **searchable item dropdown** linked to the Item Master — not free text.

### 2.5 Push MRQ Through Approval Chain

| Step | Button | Final Status |
|------|--------|--------------|
| 1 — Head | **Note** | `noted` |
| 2 — Manager | **Check** | `checked` |
| 3 — Officer | **Review** | `reviewed` |
| 4 — VP | **VP Approve** | `approved` |

### 2.6 Fulfill the Requisition

1. On the approved MRQ, click **Fulfill Requisition**
2. ✅ Status: `fulfilled`
3. ✅ **Inventory → Stock Balances:** RAW-001 decreases by 200 kg → ≈ 300 kg remaining
4. ✅ **Inventory → Stock Ledger:** new outbound transaction logged (source: MRQ)

---

## Scenario 3 — Accounts Payable: Vendor Invoice Approval and Payment

> **Story:** Two AP invoices for Chinatown Resins Inc. were auto-created when the GRs were confirmed in Scenario 1. Before production begins, finance processes these invoices — push through the 5-step approval chain, then record payment.

### 3.1 Locate the Auto-Created AP Invoices

1. Go to **Accounting → AP Invoices**
2. You should see two draft invoices for **Chinatown Resins Inc.**:
   - ₱89,640 — auto-created from GR-2026-03-00001 (498 kg × ₱180)
   - ₱360 — auto-created from GR-2026-03-00002 (2 kg × ₱180)
3. ✅ Both are in `draft` status with description `Auto-created from GR...`

> **If the invoices are missing:** Check that the queue worker ran after confirming the GRs. If needed, create manually: **AP Invoices → New**, Vendor: Chinatown Resins Inc., Fiscal Period: March 2026, AP Account: 2001, Expense Account: 6001, Invoice Date: 2026-03-18, Due Date: 2026-04-17, Net Amount: ₱89,640 (first) and ₱360 (second).

### 3.2 Push Through the 5-Step AP Approval Chain

> Open each invoice by clicking **View →** on the list, then use the action buttons on the detail page.

| Step | Button on detail page | Expected Status |
|------|----------------------|-----------------|
| 1 | **Submit for Approval** | `pending_approval` |
| 2 | **Head Note** | `head_noted` |
| 3 | **Manager Check** | `manager_checked` |
| 4 | **Officer Review** | `officer_reviewed` |
| 5 | **Approve** | `approved` |

> Repeat for both invoices. EWT is auto-computed per-invoice at 2% of net amount upon approval.

✅ After approving the ₱89,640 invoice: EWT ₱1,792.80 computed automatically  
✅ GL entry auto-posted:
- DR Materials/Expense ₱89,640
- CR Accounts Payable ₱89,640

### 3.3 Record Vendor Payment

1. Open an approved AP invoice → click **View →** from the AP Invoices list
2. Click **Record Payment**:
   - For the ₱89,640 invoice: **Amount:** ₱87,847.20 *(₱89,640 − 2% EWT ₱1,792.80)*
   - For the ₱360 invoice: **Amount:** ₱352.80 *(₱360 − 2% EWT ₱7.20)*
   - **Payment Date:** 2026-03-25
   - **Bank Account:** select the operating bank account
3. Click **Save**
4. ✅ Invoice status: `paid`
5. ✅ GL entry per invoice:
   - DR Accounts Payable (invoice net)
   - CR EWT Payable (2% EWT)
   - CR Cash / Bank (net − EWT)
6. Repeat for the second invoice
7. Go to **Accounting → General Ledger** → filter by `2001 — Accounts Payable` → ✅ closing balance = ₱0

---

## Scenario 4 — Production: Work Order Execution

> **Story:** Production runs 10,000 units of Plastic Container 500ml for an Ace Hardware order scheduled for March 28, using the 200 kg of RAW-001 issued in Scenario 2.

### 4.1 Create a Delivery Schedule

1. Go to **Production → Delivery Schedules → New**
2. Fill in:
   - **Customer:** Ace Hardware Philippines *(dropdown)*
   - **Product Item:** FGD-001 Plastic Container 500ml *(dropdown — finished goods only)*
   - **Qty Ordered:** 10,000
   - **Target Delivery Date:** 2026-03-28
   - **Type:** Local
   - **Notes:** *(optional)*
3. Click **Save**
4. ✅ Status `scheduled`; schedule visible on the board

### 4.2 Review the Bill of Materials

1. Go to **Production → Bill of Materials**
2. Open the BOM for **FGD-001 Plastic Container 500ml**
3. ✅ Component list shows RAW-001 and RAW-002 with per-unit quantities and routing steps

### 4.3 Create, Release, and Start the Production Order

1. Go to **Production → Orders → New**
2. Fill in:
   - **Product Item:** FGD-001 Plastic Container 500ml *(dropdown — select item first)*
   - **BOM:** *(auto-filters to BOMs for the selected item — select the active BOM)*
   - **Delivery Schedule:** *(optional dropdown — link to the schedule created in 4.1)*
   - **Qty Required:** 10,000
   - **Target Start Date:** 2026-03-22, **Target End Date:** 2026-03-25
   - **Notes:** *(optional)*
3. Click **Create** (status `draft`) → **Release** (status `released`) → **Start** (status `in_progress`)
4. ✅ Production order reference generated (e.g., `WO-2026-03-00001`)

### 4.4 Log Output and Complete

1. On the `in_progress` order, click **Log Output**:
   - **Qty Produced:** 10,050
   - **Qty Rejected (scrap):** 43
2. Click **Complete Work Order**
3. ✅ Status: `completed`
4. ✅ **Inventory → Stock Balances:** FGD-001 increases by **10,007 units** (10,050 − 43)
5. ✅ RAW-001 stock decreases proportionally (consumed per BOM)
6. ✅ A **Draft Outbound Delivery Receipt** is auto-created (linked to the delivery schedule)

---

## Scenario 5 — Maintenance: Corrective and Preventive Work Orders

> **Story:** Injection Moulding Machine #1 breaks down during the production run (March 22). The corrective WO is raised, resolved, and then a preventive PM schedule review is done.

### 5.1 Create a Corrective Work Order

1. Go to **Maintenance → Work Orders → New**
2. Fill in:
   - **Equipment:** Injection Moulding Machine #1
   - **Type:** Corrective, **Priority:** Critical
   - **Title:** Hydraulic system leak — production stoppage March 22
   - **Description:** Machine stopped. Hydraulic fluid leaking from main cylinder seal. Requires seal replacement.
   - **Scheduled Date:** 2026-03-22
3. Click **Save** → **Start Work**
4. ✅ Status `in_progress`; reference `WO-MAINT-2026-00001`

### 5.2 Complete the Work Order

1. Click **Complete**
2. Completion notes: *Replaced main cylinder seal (Part No: MC-SEAL-017). Pressure test passed at 250 bar. Machine operational.*
3. **Actual Completion Date:** 2026-03-22, **Labor Hours:** 3.5
4. ✅ Work order status: `completed`
5. ✅ Equipment status: `operational`; last-serviced date updated

### 5.3 Review Preventive Maintenance Schedule

1. Go to **Maintenance → Equipment**, open Injection Moulding Machine #1
2. Check linked PM schedules
3. ✅ `next_due` = `last_done_on + frequency_days`; any overdue PM schedule is flagged
4. If PM is overdue, create a preventive WO: **Maintenance → Work Orders → New** with **Type: Preventive**

---

## Scenario 6 — Mold: Shot Log and Criticality Monitor

> **Story:** The Container 500ml – Cavity 4 mold starts at 91% shot-life. After the production run, 10,050 new shots are logged. Confirm criticality escalates and a maintenance WO is auto-generated if threshold is crossed.

### 6.1 View Current Mold Status

1. Go to **Mold** (main list)
2. Locate **Container 500ml – Cavity 4**
3. ✅ Criticality badge: **CRITICAL** (≥90% utilization)
4. Note the current `current_shots` and `max_shots` values

### 6.2 Log Production Shots

1. Open the mold record → click **Log Shots**
2. Fill in:
   - **Shot Count:** 10,050 *(the number of shots fired in this run)*
   - **Log Date:** 2026-03-22
   - **Remarks:** March production run FGD-001 *(optional)*
   - **Operator:** *(optional)*
   - **Production Order:** *(optional — link to the WO from Scenario 4)*
3. Click **Save**
4. ✅ `current_shots` counter updates
5. ✅ If `current_shots >= max_shots`, a **Preventive Maintenance Work Order is auto-created** (check **Maintenance → Work Orders** — a new record linked to this mold should appear)
6. ✅ Criticality badge reflects the new percentage

---

## Scenario 7 — QC / QA: In-Process Inspection, NCR, CAPA, and Outgoing Inspection

> **Story part A (IPQC):** An in-process inspection on March 23 finds wall thickness below spec — NCR and CAPA issued. Mold re-shimmed and resolved by March 25.
> **Story part B (OQC):** Outgoing inspection on March 25 confirms finished goods meet spec before shipment.

### 7.1 IPQC Failing Inspection

1. Go to **QC / QA → Inspections → New**
2. Fill in:
   - **Stage:** IPQC
   - **Inspection Date:** 2026-03-23
   - **Item:** FGD-001 Plastic Container 500ml *(dropdown)*
   - **Qty Inspected:** 50
3. Click **Create** → on the detail page, click **Submit Results**:
   - Visual contamination: **Conforming**
   - Wall thickness: **Non-Conforming**, Actual: `1.72mm`
4. ✅ Inspection status: `failed`; reference `INS-2026-IPQC-001`

### 7.2 Raise a Non-Conformance Report (NCR)

> **Important:** The NCR form requires a **Linked Inspection** — use the failing IPQC from step 7.1.

1. Go to **QC / QA → NCRs → New**
2. Fill in:
   - **Linked Inspection:** *(dropdown — select the IPQC inspection from 7.1)*
   - **Severity:** Major *(dropdown: minor / major / critical)*
   - **Title:** Wall thickness below minimum spec — IPQC March 23
   - **Description:** IPQC found average wall thickness of 1.72mm — below the 1.80mm minimum. Suspect mold wear on Container 500ml – Cavity 4.
3. Click **Submit**
4. ✅ NCR reference generated (e.g., `NCR-2026-03-00001`); status `open`

### 7.3 Issue and Complete a CAPA

1. Open `NCR-2026-03-00001`, click **Issue CAPA**:
   - **Type:** Corrective
   - **Description:** Re-shim Container 500ml – Cavity 4 mold to restore cavity depth. Verify wall thickness with CMM before resuming. Quarantine suspect units.
   - **Due Date:** 2026-03-25
2. ✅ CAPA reference generated; NCR status: `capa_issued`
3. Click **Complete CAPA**, enter resolution: *Mold re-shimmed +0.16mm. CMM confirms 1.94mm. Trial units conforming.*
4. ✅ CAPA status: `completed`
5. Open the NCR → click **Close NCR**
6. ✅ NCR status: `closed`

### 7.4 Outgoing Quality Inspection (OQC)

1. Go to **QC / QA → Inspections → New**
2. Fill in:
   - **Stage:** OQC
   - **Inspection Date:** 2026-03-25
   - **Item:** *(dropdown — select FGD-001 Plastic Container 500ml)*
   - **Qty Inspected:** 200
   - **Template:** *(optional)*
   - **Inspector:** *(optional)*
3. Click **Create** → on detail page, click **Submit Results**:
   - Dimensional check: **Conforming**, Actual `111mm`
   - Wall thickness: **Conforming**, Actual `2.1mm`
   - Lid fit: **Conforming**
   - Visual: **Conforming**
4. ✅ Inspection status: `passed`; reference `INS-2026-OQC-001`
5. ✅ Production order status remains `completed` (not put on hold)

---

## Scenario 8 — Delivery: Outbound Shipment

> **Story:** The 10,007 finished units of FGD-001 (10,000 committed to Ace Hardware) are shipped via TRUCK-001. A draft DR was auto-created when the production order completed in Scenario 4.

### 8.1 Confirm the Auto-Created Delivery Receipt

> When the production order completed in Scenario 4, a draft outbound DR was auto-created (linked to the delivery schedule).

1. Go to **Delivery → Delivery Receipts**
2. Locate the draft outbound DR linked to Ace Hardware Philippines
3. Verify line item: FGD-001, Qty: 10,000
4. Click **Confirm**
5. ✅ DR status: `confirmed`; reference `DR-OUT-2026-00001`
6. ✅ FGD-001 stock decreases by 10,000

> **Alternative — if no auto-created DR exists:**

1. Go to **Delivery → New Delivery Receipt**
2. Fill in the header:
   - **Direction:** Outbound *(dropdown: inbound / outbound)*
   - **Customer:** Ace Hardware Philippines *(shown when Direction = Outbound)*
   - **Receipt Date:** 2026-03-28
   - **Remarks:** *(optional)*
3. Add a line item:
   - **Item:** *(dropdown — search and select FGD-001)*
   - **Qty Expected:** 10,000
   - **Qty Received:** 10,000
   - **UoM:** pcs
   - **Lot/Batch Number:** *(optional)*
4. Click **Save → Confirm**
5. ✅ DR status: `confirmed`; reference `DR-OUT-2026-00001`
6. ✅ FGD-001 stock decreases by 10,000

> **Note:** The Vehicle field is on the **Shipment** (step 8.2), not on the Delivery Receipt.

### 8.2 Create and Track a Shipment

1. Go to **Delivery → Shipments → New**
2. Fill in:
   - **Link to DR:** `DR-OUT-2026-00001`
   - **Carrier:** JRS Express, **Tracking No:** JRS-2026-032801
   - **Shipped Date:** 2026-03-28, **Estimated Arrival:** 2026-03-29
3. Click **Save** (status `pending`) → **Update Status → In Transit**
4. Update to **Delivered**, **Actual Arrival:** 2026-03-29
5. ✅ Shipment status: `delivered`

---

## Scenario 9 — Accounts Receivable: Customer Invoice and Collection

> **Story:** Bill Ace Hardware Philippines for 10,000 units at ₱28.00/unit = ₱280,000 + 12% VAT = ₱313,600 total.

### 9.1 Create and Approve the Customer Invoice

1. Go to **Accounting → AR Invoices → New**
2. Fill in:
   - **Customer:** Ace Hardware Philippines *(dropdown)*
   - **Fiscal Period:** March 2026 *(dropdown)*
   - **AR Account:** 3001 — Accounts Receivable *(dropdown)*
   - **Revenue Account:** 4001 — Revenue *(dropdown)*
   - **Invoice Date:** 2026-03-28
   - **Due Date:** 2026-04-27
   - **Subtotal:** ₱280,000.00 *(net amount before VAT)*
   - **VAT Amount:** ₱33,600 *(auto-computed at 12%; adjust if needed)*
   - **Description:** FGD-001 Plastic Container 500ml × 10,000 units @ ₱28.00 *(optional)*
3. Click **Save → Approve**
4. ✅ Invoice status: `approved`
5. ✅ GL entry auto-posted:
   - DR Accounts Receivable ₱313,600
   - CR Sales Revenue ₱280,000
   - CR Output VAT Payable ₱33,600

> **Note:** AR invoices record a **Subtotal** (not individual line items). The total displayed is Subtotal + VAT.

### 9.2 Receive Customer Payment

1. Open the approved AR invoice (from the AR Invoices list)
2. Click **Receive Payment**:
   - **Amount:** ₱313,600
   - **Payment Date:** 2026-04-05
   - **Reference:** BDO-TRF-20260405-001
3. Click **Save**
4. ✅ Invoice status: `paid`
5. ✅ GL entry:
   - DR Cash / Bank ₱313,600
   - CR Accounts Receivable ₱313,600
6. ✅ **Accounting → General Ledger** → AR account balance = ₱0

---

## Scenario 10 — Accounting: Journal Entries and Period Verification

> **Story:** Post month-end depreciation, then verify all financial reports correctly reflect the full cycle (two AP payments in Scenario 3, one AR collection in Scenario 9).

### 10.1 Post Month-End Depreciation

1. Go to **Accounting → Journal Entries → New**
2. Fill in:
   - **Date:** 2026-03-31
   - **Description:** Monthly depreciation — Injection Moulding Machine #1
   - Line 1: DR Depreciation Expense — Machinery ₱12,500
   - Line 2: CR Accumulated Depreciation — Machinery ₱12,500
3. Click **Save as Draft → Submit → Post**
4. ✅ JE status: `posted`; visible in GL for March 2026

### 10.2 VAT Ledger — Review Input and Output VAT

1. Go to **Tax → VAT Ledger**
2. Filter by **Period:** March 2026
3. ✅ Input VAT entries: from the AP invoices approved in Scenario 3 (if VAT was entered)
4. ✅ Output VAT entries: from the AR invoice in Scenario 9 — ₱33,600

### 10.3 Trial Balance

1. Go to **Accounting → Trial Balance**
2. Select period: **March 2026**
3. ✅ Total Debits = Total Credits (always)
4. Verify key account balances:

| Account | Expected |
|---------|----------|
| Cash / Bank | Net of payments received and made |
| Accounts Payable (2001) | ₱0 — fully paid in Scenario 3 |
| Accounts Receivable (3001) | ₱0 — fully collected |
| Sales Revenue (4001) | ₱280,000 |
| Output VAT Payable | ₱33,600 |

### 10.4 General Ledger — Account Drilldown

1. Go to **Accounting → General Ledger**
2. Filter by **Account:** `2001 — Accounts Payable`
3. ✅ Credit entries from AP invoice approvals (Scenario 3); debit entries from payments; closing balance ₱0
4. Filter by **Account:** `3001 — Accounts Receivable`
5. ✅ Debit from AR invoice; credit from payment; closing balance ₱0

### 10.5 Income Statement

1. Go to **Accounting → Income Statement**
2. Select period: **March 2026**
3. ✅ Revenue section: ₱280,000 (Ace Hardware net sales)
4. ✅ Expense section: includes depreciation (₱12,500) and materials expense (₱90,000)
5. ✅ Gross profit and operating profit lines populated

### 10.6 Balance Sheet

1. Go to **Accounting → Balance Sheet**
2. As of: **2026-03-31**
3. ✅ Total Assets = Total Liabilities + Equity
4. ✅ Output VAT Payable of ₱33,600 visible under current liabilities
5. ✅ Accumulated Depreciation offset applied to machinery asset

---

## Scenario 11 — ISO / IATF: Document Control and Internal Audit

> **Story:** Following the NCR in Scenario 7, the ISO team updates the incoming inspection procedure and conducts an internal audit on the Production department to close out the quality cycle.

### 11.1 Create and Approve a Controlled Document

1. Go to **ISO / IATF → Documents → New**
2. Fill in:
   - **Title:** Incoming Material Inspection Procedure Rev. 3 *(required)*
   - **Document Type:** Procedure *(dropdown: procedure / work_instruction / form / manual / policy / record)*
   - **Category:** Quality *(optional text)*
   - **Version:** 3.0 *(optional)*
   - **Owner:** *(optional — select responsible employee from dropdown)*
   - **Effective Date:** 2026-03-25
   - **Review Date:** 2027-03-25 *(optional)*
3. Click **Save → Under Review → Approve**
4. ✅ Document status: `approved`; system-generated document code visible; revision history updated

> **Note:** There is no manual Document Number field. The system auto-generates the code.

### 11.2 Plan and Conduct an Internal Audit

1. Go to **ISO / IATF → Internal Audits → New**
2. Fill in:
   - **Standard:** ISO 9001:2015 *(text input)*
   - **Audit Scope:** Production Department — Process Compliance (Clauses 8.5.1, 8.5.2, 8.6) *(include clause references in the scope text)*
   - **Lead Auditor:** *(optional dropdown — select employee)*
   - **Audit Date:** 2026-03-28
3. Click **Save** (status `planned`) → **Start Audit** (status `in_progress`) → **Complete**
4. ✅ Audit status: `completed`

### 11.3 Record a Finding and CAPA

1. On the completed audit, click **Add Finding**:
   - **Clause:** 8.5.1
   - **Description:** Labelling SOP not followed for 3 of 10 sampled containers on Production Line 1.
   - **Severity:** Minor
2. ✅ Finding recorded; CAPA is **auto-created** (triggered by the `AuditFindingCreated` event)
3. Go to **QC / QA → CAPA** — verify the auto-created CAPA linked to this audit finding
4. Click **Complete CAPA**, enter resolution: *SOP re-briefed to operators; labelling station checklist added.*
5. On the audit finding, click **Close Finding**
6. ✅ Finding status: `closed`

---

## End-to-End Sanity Checklist

After completing all scenarios, verify these summary points:

| Check | Expected Value |
|-------|----------------|
| RAW-001 stock | ≈ 300 kg (500 received − 200 issued via MRQ) |
| FGD-001 stock | 7 units (10,007 produced − 10,000 delivered; ±actual BOM consumption) |
| Accounts Payable balance (2001) | ₱0 — two invoices totalling ₱90,000 approved and paid (Scenario 3) |
| Accounts Receivable balance (3001) | ₱0 — invoice of ₱313,600 collected (Scenario 9) |
| Sales Revenue (March 2026) | ₱280,000 net |
| Output VAT Payable | ₱33,600 |
| Trial Balance | Debits = Credits |
| NCR — Wall thickness | Status `closed` (Scenario 7) |
| Mold Container 500ml – Cavity 4 | Shot counter updated; criticality badge shown (Scenario 6) |
| Maintenance WO IMM #1 | Status `completed`; equipment operational (Scenario 5) |
| Delivery Receipt | Status `confirmed`; shipment `delivered` (Scenario 8) |
| Internal Audit | Status `completed`; finding `closed`; CAPA auto-created and closed (Scenario 11) |

---

## Cross-Module Auto-Triggers Verification

These are background integrations that fire automatically. Check each one after completing the relevant scenario.

| Trigger | Where to Verify | Scenario |
|---------|----------------|-----------|
| PO Sent → Warehouse notification | Notification bell (top right) | 1.3 |
| GR Confirmed → Stock receive | Inventory → Stock Balances — RAW-001 up | 1.4 |
| 3WM passed → Draft AP invoice | Accounting → AP Invoices — one draft per confirmed GR | 1.4, 1.5 |
| AP Invoice Approved → GL entry posted | Accounting → General Ledger — DR Expense / CR AP | 3.2 |
| AP Payment Recorded → GL entry posted | Accounting → General Ledger — DR AP / CR Cash | 3.3 |
| Production Complete → Draft outbound DR | Delivery → Delivery Receipts — draft DR appears | 4.4 |
| Mold shots ≥ max → Preventive WO auto-created | Maintenance → Work Orders — new WO linked to mold | 6.2 |
| QC Pass (OQC) → Production order not put on hold | Production → Orders — order status remains `completed` | 7.4 |
| AR Invoice Approved → GL entry posted | Accounting → General Ledger — DR AR / CR Revenue | 9.1 |
| ISO Audit Finding created → CAPA auto-created | QC / QA → CAPA — CAPA linked to finding | 11.3 |

---

## Common Issues & Quick Fixes

| Symptom | Fix |
|---------|-----|
| PR form has no Title field | Correct — fill **Justification** (min 20 chars) and **Urgency** instead |
| PR line items have no item dropdown | Correct — PR uses **free-text Description**. MRQ uses the item master dropdown |
| GR form asks for PO Item ID | Use the **Receive Goods** button on the PO detail page — items are auto-populated with the correct PO item IDs. Manual entry is only needed when creating a GR from the Goods Receipts list directly |
| GR does not link to PO | Ensure PO is in `sent` or `partially_received` status before creating the GR |
| Receive Goods button not visible on PO | PO must be in `sent` or `partially_received` status — the button is hidden for `draft`, `fully_received`, `closed`, and `cancelled` POs |
| 3-way match draft invoice missing | Check that GR status is `confirmed` and queue worker is running |
| AP invoice 409 on Head Note | Ensure invoice is in `pending_approval` status — click **Submit** before **Head Note** |
| AP invoice 409 on Approve | Must complete all 5 steps in order: Submit → Head Note → Manager Check → Officer Review → Approve |
| NCR creation rejected | NCR requires a **Linked Inspection** — create the failing IPQC inspection first (step 7.1) |
| Mold log field is "Shot Count" | Correct label — not "Shots This Run". Date field is "Log Date" not "Production Date" |
| AR invoice has no line items | Correct — AR invoices record a **Subtotal** amount. Enter the net total; VAT is auto-computed |
| AP invoice has no Invoice No field | The field is **OR Number** (vendor's OR/invoice reference) — optional |
| AP invoice EWT not filling in | EWT is auto-computed from vendor setup, not a form input |
| ISO Document No not editable | Document number is system-generated — no manual input field |
| ISO Audit has no Clauses field | Include clause references in the **Audit Scope** text field |
| FGD-001 stock not updated after production | Work order must reach `completed` status |
| Auto outbound DR not created | Verify production order has a `delivery_schedule_id` linked |
| AP invoice GL accounts blank | Seeder must have CoA codes `2001` and `6001` — re-run `ChartOfAccountsSeeder` if missing |
| Output VAT not posted on AR | AR invoice must reach `approved` status |
| Trial balance debits ≠ credits | Check **Accounting → Journal Entries** for any entries stuck in `draft` or `submitted` |
| Mold preventive WO not auto-created | Confirm `current_shots >= max_shots` after logging; check queue worker is running (`php artisan queue:work`) |
| CAPA not auto-created on audit finding | Check queue worker; `AuditFindingCreated` event is queued |
| ISO document not visible | Effective date must be set; status must be `approved` |
