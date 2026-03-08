# Ogami ERP — Complete Modules Testing Guide (Superadmin)

> **Account:** `superadmin@ogamierp.local` / `SuperAdmin@12345!`
> **Environment:** `http://localhost:5173`
> ⚠️ **Before starting — LOCAL / STAGING ONLY:** `php artisan migrate:fresh --seed` then `npm run dev`
> **Queue worker required:** `php artisan queue:work` — several modules auto-create records that require the queue. Run this in a separate terminal before starting Scenario 1.
> **⛔ WARNING: `migrate:fresh` PERMANENTLY DELETES ALL DATABASE DATA.** Only run this on a local or staging environment.
> **Single account throughout** — no login/logout required between steps.

This guide covers all 12 core operational modules in one connected manufacturing cycle with no shortcuts, no bypassed approval chains, and no skipped steps.

**Procurement → Inventory → AP → Production → Maintenance → Mold → QC → Delivery → AR → Accounting → Tax → ISO**

Every approval chain is executed in full. Every status transition is verified. The story flows in real calendar order.

---

## Modules Covered

| # | Scenario | Module | Key Workflow |
|---|----------|--------|-------------|
| 0 | Master Data Setup | — | Bank, Vendor, Customer, Items, BOM |
| 1 | Procurement | Procurement | PR → 4-step approval → PO → 2× GR with 3-way match |
| 2 | Inventory | Inventory | IQC inspection + stock verification |
| 3 | Accounts Payable | AP | 5-step invoice approval × 2 invoices → payment |
| 4 | Production | Production | Delivery Schedule → WO → auto-MRQ 6-step chain → Start → Output → Complete |
| 5 | Maintenance | Maintenance | Equipment records → Corrective WO → PM Schedule |
| 6 | Mold | Mold | Mold record → shot log → criticality → auto-preventive WO |
| 7 | QC / QA | QC | IPQC fail → NCR raise → CAPA complete → close → OQC pass |
| 8 | Delivery | Delivery | Auto-DR confirm → Shipment → In Transit → Delivered |
| 9 | Accounts Receivable | AR | Auto-invoice locate → Approve → Receive Payment |
| 10 | Accounting | Accounting | Month-end JE → Trial Balance → GL drilldown → Reports |
| 11 | Tax | Tax | VAT Ledger verification → Close Period |
| 12 | ISO | ISO | Controlled Document → Internal Audit → Finding → CAPA → close |

---

## Real-Life Timeline

```
[March 15] PR raised for 500 kg PP Resin → approval chain → PO sent
[March 18] GR-1: 498 kg received → 3-way match → stock +498 kg → AP invoice auto-created
[March 18] IQC inspection on received PP Resin → passed
[March 21] GR-2: 2 kg received → fully received → second AP invoice auto-created
[March 22] AP invoices approved and paid (₱90,000 total)
[March 22] Equipment breakdown → corrective maintenance WO raised → resolved same day
[March 22] Mold shot log recorded (10,050 shots) → criticality CRITICAL → preventive WO auto-created
[March 22] Production WO created for 10,000 units → Released → auto-MRQ raised
[March 22] Auto-MRQ approved through 6-step chain → Fulfilled → stock -~200 kg
[March 22] Production WO started → in progress
[March 23] IPQC inspection: wall thickness fails → NCR raised → CAPA auto-created
[March 25] CAPA completed and NCR closed; OQC inspection passes
[March 28] Production WO: output logged (10,050 produced, 43 rejected) → Completed
[March 28] Auto-DR confirmed → Shipment created → in transit → delivered
[March 28] AR invoice auto-created on shipment delivered
[April 5]  AR invoice approved → customer payment received
[March 31] Month-end JE posted; Trial Balance verified; VAT Ledger closed
[March 28] ISO document approved; internal audit completed; finding closed
```

---

## What Is Seeded vs. What You Create Manually

### Seeded by `migrate:fresh --seed`

| Category | What |
|----------|------|
| Chart of Accounts | 16 accounts: 1001 Cash, 2001 AP, 3001 AR, 4001 Revenue, 5001 Wages, 6001 Expense, 2105 Output VAT Payable, 2106 BIR VAT Remittable, etc. |
| Fiscal Periods | Nov 2025, Dec 2025, Jan 2026 (closed), Feb 2026, Mar 2026 (open) |
| Rate Tables | SSS, PhilHealth, Pag-IBIG, tax brackets, OT multipliers, holiday calendar |
| RBAC + User Accounts | Roles, permissions, 21 user accounts |
| Org Structure | Departments, positions, salary grades |
| Fleet Vehicles | TRUCK-001, TRUCK-002, VAN-001 |

### Created manually in Scenario 0

Bank account, vendor, customer, item categories, item masters, warehouse location, bill of materials.

---

## Scenario 0 — Setup: Create All Business Master Data

> Run this **once on a fresh database** before any other scenario. All other scenarios depend on this data.

### 0.1 Create a Bank Account

> Required for: AP payments (Scenario 3) and AR collections (Scenario 9).

1. Go to **Banking → Bank Accounts → New**
2. Fill in:
   - **Name:** BDO
   - **Account Number:** 0000-1234-5678
   - **Bank Name:** Banco de Oro (BDO)
   - **Account Type:** Checking
   - **GL Account:** 1001 — Cash in Bank *(select from dropdown — seeded automatically)*
   - **Opening Balance:** 500000
   - **Active:** leave checked
3. Click **Save**
4. ✅ Bank account visible in payment dropdowns

### 0.2 Create a Vendor

> Required for: Purchase Orders (Scenario 1), AP invoices (Scenario 3).

1. Go to **Accounting → AP Vendors → New**
2. Fill in:
   - **Vendor Name:** Chinatown Resins Inc.
   - **TIN:** 000-123-456-000
   - **Contact Person:** Juan Dela Cruz
   - **Email:** sales@chinatownresins.test
   - **Phone:** +63 2 8888 0001
   - **Address:** 12 Resin Street, Tondo, Manila
   - **Payment Terms:** NET30
3. Click **Save**
4. ✅ Vendor available in PO and AP invoice dropdowns

### 0.3 Create a Customer

> Required for: Delivery Schedule (Scenario 4), AR invoice (Scenario 9).

1. Go to **Accounting → AR Customers → New**
2. Fill in:
   - **Name:** Ace Hardware Philippines
   - **TIN:** 000-987-654-000
   - **Credit Limit (₱):** 500000
   - **Email:** procurement@acehw.test
   - **Phone:** +63 2 8999 0002
   - **Contact Person:** Maria Cruz
   - **Address:** 1 Hardware Ave, Pasig, Metro Manila
3. Click **Save**
4. ✅ Customer available in delivery schedule and AR invoice dropdowns

### 0.4 Create an Item Category

1. Go to **Inventory → Item Categories → + New Category**
2. Fill in:
   - **Code:** RAW-MAT
   - **Name:** Raw Materials
   - **Description:** Plastic pellets and raw resin inputs
3. Click **Save**
4. ✅ Category `RAW-MAT` available in item master Category dropdown

### 0.5 Create Item Masters

Go to **Inventory → Item Master → + New** for each item below.

> Item codes are **auto-generated** (format: `ITEM-000001`). The code appears after saving — there is no manual code field.

**Item 1 — PP Resin Natural**
- **Category:** Raw Materials
- **Name:** PP Resin Natural
- **Type:** Raw Material
- **Unit of Measure:** kg
- **Reorder Point:** 500 · **Reorder Qty:** 2000
- Check ✅ **Requires Incoming Quality Control (IQC)**
- Click **Save**

**Item 2 — HDPE Resin Black**
- **Category:** Raw Materials
- **Name:** HDPE Resin Black
- **Type:** Raw Material
- **Unit of Measure:** kg
- **Reorder Point:** 300 · **Reorder Qty:** 1000
- Check ✅ **Requires Incoming Quality Control (IQC)**
- Click **Save**

**Item 3 — Plastic Container 500ml**
- **Category:** Raw Materials *(use the same category — Finished Good is fine if available)*
- **Name:** Plastic Container 500ml
- **Type:** Finished Good
- **Unit of Measure:** pcs
- **Reorder Point:** 1000 · **Reorder Qty:** 5000
- Check ✅ **Requires Incoming Quality Control (IQC)**
- Click **Save**

✅ All three items visible in **Inventory → Item Master** with auto-generated codes

### 0.6 Create a Warehouse Location

1. Go to **Inventory → Warehouse Locations → New**
2. Fill in:
   - **Code:** WH-A1
   - **Name:** Warehouse A – Rack 1
   - **Zone:** A
   - **Bin/Rack:** Rack-01
3. Click **Save**
4. ✅ Location `WH-A1` available in stock ledger entries and MRQ fulfillment

### 0.7 Create a Bill of Materials for Plastic Container 500ml

> Required for: Production Order (Scenario 4). All three items from step 0.5 must exist first.

1. Go to **Production → Bill of Materials → New**
2. Header:
   - **Product Item:** Plastic Container 500ml *(dropdown — finished goods only)*
   - **Version:** 1.0
   - **Notes:** Injection-moulded 500ml PP container. 20g gross weight per unit.
3. Click **Add Component** → first row:
   - **Component Item:** PP Resin Natural
   - **Qty/Unit:** 0.0192
   - **UoM:** kg
   - **Scrap %:** 4
4. Click **Add Component** → second row:
   - **Component Item:** HDPE Resin Black
   - **Qty/Unit:** 0.0008
   - **UoM:** kg
   - **Scrap %:** 2
5. Click **Save** → then click **Activate**
6. ✅ BOM v1.0 status `active`; components listed; available in Production Order BOM dropdown

---

## Scenario 1 — Procurement: PR → PO → Goods Receipt

> **Story:** The warehouse is low on PP Resin. A purchase request is raised for 500 kg, pushed through the full 4-step approval chain, converted to a PO, and goods are received in two separate deliveries.

### 1.1 Create a Purchase Request

1. Go to **Procurement → Purchase Requests → New**
2. Fill in:
   - **Department:** Production
   - **Urgency:** Normal
   - **Justification:** Warehouse stock of PP Resin Natural is below safety level. Requesting 500 kg to cover the March production run for FGD-001. *(min 20 characters)*
3. Click **+ Add Item** to add a line item:
   - **Description:** PP Resin Natural *(free-text — PR line items do not use the item master dropdown)*
   - **UoM:** kg
   - **Qty:** 500
   - **Unit Cost:** 180.00
   - **Est. Total:** ₱90,000.00 *(auto-computed)*
4. Click **Save** — status changes to `draft`
5. On the PR detail page, click **Submit for Approval**
6. ✅ Status: `submitted` · Reference: `PR-2026-03-00001`

### 1.2 Push Through the 4-Step PR Approval Chain

Open the PR detail page and click through the approval buttons in order. Each button opens a comment modal — comments are optional (but recommended for realism).

| Step | Button | Enter Comment | Final Status |
|------|--------|---------------|--------------|
| 1 | **Note (Acknowledge)** | *Stock below reorder level* | `noted` |
| 2 | **Check (Verify)** | *Approved for purchase* | `checked` |
| 3 | **Review** | *Chinatown Resins confirmed, lead time 3 days* | `reviewed` |
| 4 | **Final Approve** | *(optional)* | `vp_approved` |

✅ **Create PO** button appears on the PR detail page after Final Approve.

### 1.3 Convert the Approved PR to a Purchase Order

1. On the `vp_approved` PR detail page, click **Create PO** — the PO form opens pre-filled with PR line items
2. Fill in the PO header fields:
   - **Vendor:** Chinatown Resins Inc.
   - **Delivery Date:** 2026-03-18
   - **Payment Terms:** Net 30
3. For each line item, link the Item Master entry:
   - In the **Item Master** dropdown on the first line, search and select **PP Resin Natural**
   - **Unit Price:** 180.00 *(confirm or enter)*
4. Click **Create PO** — status: `draft`
5. On the PO detail page, click **Send to Vendor** — status: `sent`
6. ✅ Status: `sent` · Reference: `PO-2026-03-00001`
7. ✅ PR status updates to `converted_to_po`

> ⚠️ **Item Master is required on every PO line.** PRs use free-text descriptions; the PO is the point where each line must be linked to an Item Master record. The item master link drives stock updates when GRs are confirmed.

### 1.4 Record the First Goods Receipt (498 kg — short delivery)

> **Queue prerequisite:** Stock balance updates and AP invoice auto-creation happen in the background queue. Make sure `php artisan queue:work` is running before confirming GRs.

1. On the `sent` PO detail page, click **Receive Goods**
2. In the GR form:
   - **Received Date:** 2026-03-18
   - **Qty Received:** 498 *(change from 500 — the vendor shipped short)*
3. Click **Record Goods Receipt** — status: `draft`
4. On the GR detail page, click **Confirm Receipt & Run 3-Way Match**
5. ✅ GR status: `confirmed` · Reference: `GR-2026-03-00001`
6. ✅ PO status: `partially_received`
7. ✅ **Inventory → Stock Balances:** PP Resin Natural increases by 498 kg *(requires queue worker)*
8. ✅ **Accounting → AP Invoices:** a draft AP invoice for Chinatown Resins Inc. auto-created *(requires queue worker)*

### 1.5 Record the Second Goods Receipt (remaining 2 kg)

1. On the `partially_received` PO detail page, click **Receive Goods**
2. In the GR form:
   - **Received Date:** 2026-03-21
   - **Qty Received:** 2 *(pre-filled from outstanding balance)*
3. Click **Record Goods Receipt** → **Confirm Receipt & Run 3-Way Match**
4. ✅ GR status: `confirmed` · Reference: `GR-2026-03-00002`
5. ✅ PO status: `fully_received`
6. ✅ **Inventory → Stock Balances:** PP Resin Natural total = 500 kg
7. ✅ Second draft AP invoice for Chinatown Resins Inc. auto-created (₱360)

> **Two draft AP invoices now exist in Accounting → AP Invoices:**
> - GR-2026-03-00001 → ₱89,640 (498 kg × ₱180)
> - GR-2026-03-00002 → ₱360 (2 kg × ₱180)

---

## Scenario 2 — Inventory: Incoming Inspection and Stock Verification

> **Story:** On the day materials arrive (March 18), QC performs an incoming quality inspection before materials are used in production. The warehouse team then verifies on-hand balances and stock ledger entries.

### 2.1 Perform the Incoming Material Inspection (IQC)

1. Go to **QC / QA → Inspections → New**
2. Fill in the header:
   - **Stage:** IQC — Incoming Quality Control
   - **Inspection Date:** 2026-03-18
   - **Item:** PP Resin Natural *(search and select from dropdown)*
   - **Qty Inspected:** 50
   - **Template:** — None — *(leave blank)*
   - **Inspector:** *(optional — select any employee)*
3. Click **Create Inspection** — status: `open`; reference: `INS-2026-IQC-001`
4. On the inspection detail page, click **Record Results**
5. The results form opens inline. Add criteria rows using **+ Add Row**:

   | Criterion | Actual Value | Conforming? | Remarks |
   |-----------|-------------|-------------|---------|
   | Visual contamination | clean | **Pass** | *(optional)* |
   | Moisture content | 0.03% | **Pass** | *(optional)* |
   | Melt Flow Index | 13.2 | **Pass** | *(optional)* |

   > For each row: type the criterion name, enter the actual value, then click the **Pass** or **Fail** toggle button.

6. At the bottom of the results form:
   - **Qty Passed:** 50
   - **Qty Failed:** 0 *(auto-computed or enter 0)*
7. Click **Submit Results**
8. ✅ Inspection status: `passed`; reference `INS-2026-IQC-001`

### 2.2 Verify Stock Balance

1. Go to **Inventory → Stock Balances**
2. Search for or filter by **PP Resin Natural**
3. ✅ On-hand quantity = 500 kg *(498 from GR-1 + 2 from GR-2)*

### 2.3 Check the Stock Ledger

1. Go to **Inventory → Stock Ledger**
2. Filter by **Item:** PP Resin Natural
3. ✅ Two inbound transactions visible:
   - GR-2026-03-00001: +498 kg
   - GR-2026-03-00002: +2 kg
4. ✅ Running balance column shows cumulative totals correctly

---

## Scenario 3 — Accounts Payable: Invoice Approval and Payment

> **Story:** Two AP invoices for Chinatown Resins Inc. were auto-created when the GRs were confirmed in Scenario 1. Finance pushes both invoices through the full 5-step approval chain, then records payment for each. This happens before production starts.

### 3.1 Locate the Auto-Created AP Invoices

1. Go to **Accounting → AP Invoices**
2. Find two draft invoices for **Chinatown Resins Inc.**:
   - Invoice 1: ₱89,640.00 (498 kg × ₱180 — from GR-2026-03-00001)
   - Invoice 2: ₱360.00 (2 kg × ₱180 — from GR-2026-03-00002)
3. ✅ Both have status `draft` with description referencing the GR number

> **If invoices are missing:** Check that the queue worker was running when the GRs were confirmed. If needed, create manually: **AP Invoices → New**, Vendor: Chinatown Resins Inc., Fiscal Period: March 2026, AP Account: 2001, Expense Account: 6001, Invoice Date: 2026-03-18, Due Date: 2026-04-17, Net Amount: ₱89,640 (first) and ₱360 (second).

### 3.2 Push Invoice 1 (₱89,640) Through the 5-Step Approval Chain

Click **View →** on the ₱89,640 invoice to open the detail page, then click each button in order:

| Step | Button | Final Status |
|------|--------|--------------|
| 1 | **Submit for Approval** | `pending_approval` |
| 2 | **Head Note** | `head_noted` |
| 3 | **Manager Check** | `manager_checked` |
| 4 | **Officer Review** | `officer_reviewed` |
| 5 | **Approve** | `approved` |

✅ After Approve: EWT ₱1,792.80 auto-computed (2% of ₱89,640)
✅ GL entry auto-posted:
- DR Materials Expense 6001 ₱89,640
- CR Accounts Payable 2001 ₱89,640

### 3.3 Record Payment for Invoice 1

1. On the `approved` ₱89,640 invoice, scroll down to the **Record Payment** section
2. Fill in:
   - **Amount:** 87,847.20 *(₱89,640 − 2% EWT ₱1,792.80 = net cash outflow)*
   - **Payment Date:** 2026-03-25
   - **Bank Account:** BDO *(select from dropdown)*
3. Click **Save**
4. ✅ Invoice status: `paid`
5. ✅ GL entry:
   - DR Accounts Payable ₱89,640
   - CR EWT Payable ₱1,792.80
   - CR Cash / Bank ₱87,847.20

### 3.4 Push Invoice 2 (₱360) Through the 5-Step Approval Chain

Repeat the same 5-step process as step 3.2 for the ₱360 invoice:

| Step | Button | Final Status |
|------|--------|--------------|
| 1 | **Submit for Approval** | `pending_approval` |
| 2 | **Head Note** | `head_noted` |
| 3 | **Manager Check** | `manager_checked` |
| 4 | **Officer Review** | `officer_reviewed` |
| 5 | **Approve** | `approved` |

✅ EWT ₱7.20 auto-computed

### 3.5 Record Payment for Invoice 2

1. On the `approved` ₱360 invoice, scroll to **Record Payment**
2. Fill in:
   - **Amount:** 352.80 *(₱360 − 2% EWT ₱7.20)*
   - **Payment Date:** 2026-03-25
   - **Bank Account:** BDO
3. Click **Save**
4. ✅ Invoice status: `paid`

### 3.6 Verify AP Balance is Zero

1. Go to **Financial Reports → General Ledger**
2. Select **Account:** `2001 — Accounts Payable`, **From:** 2026-03-01, **To:** 2026-03-31
3. Click **Run Report**
4. ✅ Closing balance = ₱0 (both invoices fully paid)

---

## Scenario 4 — Production: Delivery Schedule → Work Order → Auto-MRQ → Output Log

> **Story:** Production runs 10,000 units of Plastic Container 500ml for Ace Hardware. A Delivery Schedule is created first, the WO is released (which auto-creates a Material Requisition), the MRQ is fully approved and fulfilled to formally issue stock, and then the WO is started, output logged, and completed.

### 4.1 Create a Delivery Schedule

1. Go to **Production → Delivery Schedules → New**
2. Fill in:
   - **Customer:** Ace Hardware Philippines *(dropdown)*
   - **Product Item:** Plastic Container 500ml *(dropdown — finished goods only)*
   - **Qty Ordered:** 10,000
   - **Unit Price (₱):** 28.00 *(used to compute the AR invoice subtotal when the shipment is delivered)*
   - **Target Delivery Date:** 2026-03-28
   - **Type:** Local
3. Click **Save**
4. ✅ Status `open`; schedule visible on board

### 4.2 Review the Bill of Materials

1. Go to **Production → Bill of Materials**
2. Open the BOM for **Plastic Container 500ml**
3. ✅ Two active components: PP Resin Natural (0.0192 kg, 4% scrap) and HDPE Resin Black (0.0008 kg, 2% scrap)

### 4.3 Create the Production Work Order

1. Go to **Production → Orders → New**
2. Fill in:
   - **Product Item:** Plastic Container 500ml *(select item first)*
   - **BOM:** *(auto-filters to BOMs for the selected item — select the active v1.0 BOM)*
   - **Delivery Schedule:** *(optional — select the schedule from step 4.1)*
   - **Qty Required:** 10,000
   - **Target Start Date:** 2026-03-22
   - **Target End Date:** 2026-03-25
3. Click **Create** — status: `draft`; reference: `WO-2026-03-00001`

### 4.4 Release the Work Order and Handle the Auto-MRQ

1. On the `draft` WO detail page, click **Release**
2. ✅ WO status: `released`
3. ✅ An **auto-generated Material Requisition** is created in `draft` state (e.g., `MRQ-2026-03-00001`) — this is the formal stock issuance mechanism

> **The auto-MRQ must be fully approved and fulfilled before starting the WO.** This ensures materials are formally deducted from inventory before production begins.

#### Push the Auto-MRQ Through the Full 6-Step Approval Chain

4. Go to **Inventory → Requisitions** — find the newly created MRQ (status `draft`, purpose includes the WO reference)
5. Open the MRQ detail page
6. Complete each step in order:

   | Step | Button | Status After |
   |------|--------|--------------|
   | 1 | **Submit for Approval** | `submitted` |
   | 2 | **Note** → then **Confirm Note** | `noted` |
   | 3 | **Check** → then **Confirm Check** | `checked` |
   | 4 | **Review** → then **Confirm Review** | `reviewed` |
   | 5 | **VP Approve** → then **Confirm Approve** | `approved` |

   > Steps 2–5: the first click reveals a comments textarea and changes the button to "Confirm [Action]". Add optional comments then click the Confirm button to proceed.

7. For **Fulfill (Issue Stock)** (step 6):
   - Click **Fulfill (Issue Stock)** — a **"Issue From Location"** dropdown appears
   - Select **Warehouse A – Rack 1** *(WH-A1)*
   - Click **Confirm Fulfill**
   - ✅ MRQ status: `fulfilled`
   - ✅ **Inventory → Stock Balances:** PP Resin Natural decreases by approximately 200 kg (from BOM qty × 10,000 units)

### 4.5 Start the Work Order

1. Return to **Production → Orders** → open `WO-2026-03-00001` (status `released`)
2. Click **Start Production**
3. ✅ WO status: `in_progress`

### 4.6 Log Production Output

1. On the `in_progress` WO detail page, click **Log Output**
2. The output log form appears inline — fill in:
   - **Shift:** Shift A
   - **Date:** 2026-03-28
   - **Operator:** *(select any employee from the dropdown)*
   - **Qty Produced:** 10,050
   - **Qty Rejected:** 43
   - **Remarks:** March production run — FGD-001 *(optional)*
3. Click **Submit Log**
4. ✅ Output log appears in the Output Logs table on the WO detail page
5. ✅ Progress bar updates (qty produced / qty required)

### 4.7 Mark the Work Order Complete

1. On the WO detail page (status `in_progress`), click **Mark Complete**
2. ✅ WO status: `completed`
3. ✅ **Inventory → Stock Balances:** Plastic Container 500ml increases by **10,007 units** (10,050 produced − 43 rejected)
4. ✅ PP Resin Natural stock decreases further (consumed per BOM)
5. ✅ A **Draft Outbound Delivery Receipt** is auto-created and linked to the delivery schedule *(requires queue worker)*

---

## Scenario 5 — Maintenance: Equipment, Corrective WO, PM Schedule

> **Story:** Injection Moulding Machine #1 breaks down during the production run on March 22. Equipment records are created, the corrective WO is raised and resolved, and a preventive maintenance schedule is added.

### 5.0 Create Equipment Records

**Equipment 1:**
1. Go to **Maintenance → Equipment → New**
2. Fill in:
   - **Name:** Injection Moulding Machine #1
   - **Category:** Production
   - **Manufacturer:** Engel · **Model No:** ES200/50 · **Serial No:** EM-2018-00123
   - **Location:** Production Floor A
   - **Date Commissioned:** 2018-06-01
   - **Status:** Operational
3. Click **Save** → ✅ Equipment record created

**Equipment 2:**
1. Click **New** again
2. Fill in:
   - **Name:** Hydraulic Press #3
   - **Category:** Production
   - **Manufacturer:** Schuler · **Model No:** HP-320 · **Serial No:** HP-2015-00789
   - **Location:** Production Floor B
   - **Date Commissioned:** 2015-03-20
   - **Status:** Operational
3. Click **Save** → ✅ Both equipment records listed in Maintenance → Equipment

### 5.1 Raise a Corrective Work Order

1. Go to **Maintenance → Work Orders → New**
2. Fill in:
   - **Equipment:** Injection Moulding Machine #1 *(dropdown)*
   - **Type:** Corrective
   - **Priority:** Critical
   - **Title:** Hydraulic system leak — production stoppage March 22
   - **Description:** Machine stopped. Hydraulic fluid leaking from main cylinder seal. Requires seal replacement.
   - **Scheduled Date:** 2026-03-22
3. Click **Save** — status: `pending`; reference: `WO-MAINT-2026-00001`
4. On the WO detail page, click **Start Work**
5. ✅ WO status: `in_progress`

### 5.2 Complete the Work Order

1. On the `in_progress` WO detail page, click **Complete**
2. The completion form appears inline — fill in:
   - **Completion Notes** *(required)*: Replaced main cylinder seal (Part No: MC-SEAL-017). Pressure test passed at 250 bar. Machine operational.
   - **Actual Completion Date:** 2026-03-22
   - **Labor Hours:** 3.5
3. Click **Submit** (or the form save button)
4. ✅ WO status: `completed`
5. ✅ Equipment status updated to `operational`

### 5.3 Create a Preventive Maintenance Schedule

1. Go to **Maintenance → Equipment** → open **Injection Moulding Machine #1**
2. Click **Add PM Schedule** (or go to **Maintenance → PM Schedules → New**):
   - **Equipment:** Injection Moulding Machine #1
   - **Task Name:** Monthly Lubrication Service
   - **Frequency (days):** 30
   - **Last Done On:** 2026-03-22
3. Click **Save**
4. ✅ PM Schedule created; **Next Due** = 2026-04-21 (last_done_on + 30 days)
5. ✅ Schedule listed under the equipment's PM tab

---

## Scenario 6 — Mold: Shot Log and Criticality Monitor

> **Story:** Create the Container 500ml – Cavity 4 mold with a low max-shot ceiling so the production run (10,050 shots) crosses the threshold. This tests the auto-criticality badge and auto-generated preventive WO.

### 6.0 Create the Mold Record

1. Go to **Mold → New**
2. Fill in:
   - **Name:** Container 500ml – Cavity 4
   - **Description:** 4-cavity mould for 500ml PP container
   - **Cavity Count:** 4
   - **Material:** P20 Tool Steel
   - **Location:** Mold Room Rack A
   - **Max Shots:** 10,000 *(intentionally set below the production run of 10,050 to trigger the threshold)*
   - **Status:** Active
3. Click **Save**
4. ✅ Mold record created; `current_shots` = 0; criticality: 0%

### 6.1 View Baseline Mold Status

1. Go to **Mold** (main list)
2. Locate **Container 500ml – Cavity 4**
3. ✅ `current_shots` = 0, `max_shots` = 10,000, criticality 0% — note baseline before logging

### 6.2 Log Production Shots

1. Open the mold record → click **Log Shots**
2. Fill in:
   - **Shot Count:** 10,050
   - **Log Date:** 2026-03-22
   - **Remarks:** March production run FGD-001 *(optional)*
   - **Operator:** *(optional)*
   - **Production Order:** *(optional — link to WO-2026-03-00001)*
3. Click **Save**
4. ✅ `current_shots` updates to 10,050 (exceeds `max_shots` of 10,000)
5. ✅ Criticality badge: **CRITICAL** (>100%)
6. ✅ Go to **Maintenance → Work Orders** — a new **Preventive Maintenance WO** linked to this mold appears *(requires queue worker)*

---

## Scenario 7 — QC / QA: IPQC, NCR, CAPA, and OQC

> **Story part A (IPQC):** An in-process inspection on March 23 finds wall thickness below spec — NCR auto-creates a CAPA. The CAPA is resolved and the NCR is closed by March 25.
> **Story part B (OQC):** Outgoing inspection on March 25 confirms finished goods meet spec before shipment.

### 7.1 IPQC Inspection — Failing Result

1. Go to **QC / QA → Inspections → New**
2. Fill in:
   - **Stage:** IPQC
   - **Inspection Date:** 2026-03-23
   - **Item:** Plastic Container 500ml *(search and select from dropdown)*
   - **Qty Inspected:** 50
   - **Template:** — None —
3. Click **Create Inspection** — status: `open`; reference: `INS-2026-IPQC-001`
4. On the detail page, click **Record Results**
5. The results form opens inline. Add criteria rows using **+ Add Row**:

   | Criterion | Actual Value | Conforming? | Remarks |
   |-----------|-------------|-------------|---------|
   | Visual contamination | clean | **Pass** | *(optional)* |
   | Wall thickness | 1.72mm | **Fail** | Below 1.80mm minimum |

   > Click the **Pass** or **Fail** toggle button on each row.

6. Set quantities:
   - **Qty Passed:** 0
   - **Qty Failed:** 50
7. Click **Submit Results**
8. ✅ Inspection status: `failed`; reference `INS-2026-IPQC-001`

### 7.2 Raise a Non-Conformance Report (NCR)

> An NCR requires a **Linked Inspection** — use the failing IPQC from step 7.1.

1. Go to **QC / QA → NCRs** (the list page loads)
2. Click **New NCR** in the top-right area
3. Fill in all required fields:

   | Field | Value |
   |-------|-------|
   | **Linked Inspection** | Select `INS-2026-IPQC-001 — IPQC — 2026-03-23 — …` from dropdown |
   | **Severity** | Major |
   | **Title** | Wall thickness below minimum spec — IPQC March 23 |
   | **Description** | IPQC found average wall thickness of 1.72mm — below the 1.80mm minimum. Suspect mold wear on Container 500ml – Cavity 4. |

4. Click **Raise NCR**
5. ✅ NCR detail page loads; reference `NCR-2026-03-00001`; status badge: **major** / **open**
6. ✅ NCR status auto-transitions to `capa_issued` and a draft **CAPA Action** is auto-created in the CAPA Actions section *(requires queue worker)*

### 7.3 Complete the Auto-Created CAPA

1. On the NCR detail page for `NCR-2026-03-00001`, confirm the status badge shows **capa issued**
2. Scroll to the **CAPA Actions** section — the auto-created CAPA entry is visible with:
   - Type: **corrective**
   - Status: **open**
   - Description: *Auto-generated from NCR-2026-03-00001. Update with specific corrective action details before marking complete.*
   - Due Date: 14 days from NCR creation
3. Click the **Complete** button on the CAPA row

   > If a confirmation dialog or notes input appears, enter resolution notes: *Re-shimmed Container 500ml – Cavity 4 mold. CMM verified wall thickness at 1.92mm. Suspect units quarantined and re-inspected.*

4. ✅ CAPA status badge updates to **completed**

> **If no auto-CAPA was created** (queue worker not running): Click **Issue CAPA** in the NCR page header. Fill in Type: Corrective, Description: (see above), Due Date: 2026-03-25, then proceed from step 3.

### 7.4 Close the NCR

1. On the NCR detail page, click **Close NCR** (button in the page header)
2. ✅ NCR status badge changes to **closed**
3. ✅ A **Closed At** date appears in the NCR Details card

### 7.5 Outgoing Quality Inspection (OQC)

1. Go to **QC / QA → Inspections → New**
2. Fill in:
   - **Stage:** OQC
   - **Inspection Date:** 2026-03-25
   - **Item:** Plastic Container 500ml
   - **Qty Inspected:** 200
3. Click **Create Inspection** → status `open`; reference `INS-2026-OQC-001`
4. On the detail page, click **Record Results**
5. Add criteria rows — all conforming:

   | Criterion | Actual Value | Conforming? |
   |-----------|-------------|-------------|
   | Dimensional check | 111mm | **Pass** |
   | Wall thickness | 2.1mm | **Pass** |
   | Lid fit | Snug, no gap | **Pass** |
   | Visual appearance | Clean, no flash | **Pass** |

6. **Qty Passed:** 200 · **Qty Failed:** 0
7. Click **Submit Results**
8. ✅ Inspection status: `passed`; reference `INS-2026-OQC-001`

---

## Scenario 8 — Delivery: Outbound Shipment

> **Story:** The 10,007 finished units are ready for dispatch. A draft Delivery Receipt was auto-created when the production order completed in Scenario 4. The DR is confirmed, a shipment is created and tracked through delivery.

### 8.1 Confirm the Auto-Created Delivery Receipt

1. Go to **Delivery → Delivery Receipts**
2. Locate the draft outbound DR linked to **Ace Hardware Philippines** *(created automatically when the production order completed in Scenario 4)*
3. Verify:
   - Direction: Outbound
   - Customer: Ace Hardware Philippines
   - Line item: Plastic Container 500ml, Qty: 10,000
4. Open the DR and click **Confirm**
5. ✅ DR status: `confirmed` · Reference: `DR-2026-03-00001`
6. ✅ **Inventory → Stock Balances:** Plastic Container 500ml decreases by 10,000

> **If no auto-DR exists** (queue worker not running or WO had no linked delivery schedule):
> 1. Go to **Delivery → New Delivery Receipt**
> 2. **Direction:** Outbound · **Customer:** Ace Hardware Philippines · **Receipt Date:** 2026-03-28
> 3. Add line: **Item:** Plastic Container 500ml · **Qty Expected:** 10,000 · **Qty Received:** 10,000 · **UoM:** pcs
> 4. Click **Create Receipt** → on detail page, click **Confirm Receipt**

### 8.2 Create and Track a Shipment

1. Go to **Delivery → Shipments → New Shipment** (button top-right)
2. Fill in:
   - **Delivery Receipt:** DR-2026-03-00001 — Ace Hardware Philippines *(dropdown — confirmed DRs only)*
   - **Carrier:** JRS Express
   - **Tracking No.:** JRS-2026-032801
   - **Shipped Date:** 2026-03-28
   - **Estimated Arrival:** 2026-03-29
3. Click **Create Shipment** — status: `pending`
4. On the Shipments list, click **Mark In Transit** on the row → status: `in_transit`
5. Click **Mark Delivered** → enter **Actual Arrival:** 2026-03-29
6. ✅ Shipment status: `delivered`
7. ✅ A **Draft Customer Invoice** is auto-created for Ace Hardware Philippines *(requires queue worker)*

---

## Scenario 9 — Accounts Receivable: Invoice Approval and Collection

> **Story:** Bill Ace Hardware Philippines for 10,000 units at ₱28.00/unit. The AR invoice was auto-created when the shipment was marked delivered. Approve it and record the customer payment.

### 9.1 Locate the Auto-Created AR Invoice and Approve It

1. Go to **Accounting → AR Invoices**
2. Locate the draft invoice for **Ace Hardware Philippines** *(status `draft`, description references the shipment)*
3. Verify the amounts:
   - **Subtotal:** ₱280,000.00 *(10,000 units × ₱28.00 from the delivery schedule)*
   - **VAT Amount:** ₱33,600.00 *(12% of ₱280,000)*
   - **Total:** ₱313,600.00
4. Click the **Approve** button on the invoice row (or open the invoice detail page and click **Approve**)
5. ✅ Invoice status: `approved`
6. ✅ GL entry auto-posted:
   - DR Accounts Receivable 3001 ₱313,600
   - CR Sales Revenue 4001 ₱280,000
   - CR Output VAT Payable ₱33,600

> **If the invoice is missing** (queue worker not running, or delivery schedule had no Unit Price):
> - Go to **Accounting → AR Invoices → New**
> - **Customer:** Ace Hardware Philippines · **Fiscal Period:** March 2026
> - **AR Account:** 3001 · **Revenue Account:** 4001
> - **Invoice Date:** 2026-03-28 · **Due Date:** 2026-04-27
> - **Subtotal:** 280,000.00 · **VAT Amount:** 33,600.00
> - Click **Save → Approve**

### 9.2 Receive Customer Payment

1. Open the `approved` AR invoice
2. Click **Receive Payment** (button or section on the detail page)
3. Fill in:
   - **Amount:** 313,600.00
   - **Payment Date:** 2026-04-05
   - **Reference:** BDO-TRF-20260405-001
4. Click **Save**
5. ✅ Invoice status: `paid`
6. ✅ GL entry:
   - DR Cash / Bank 1001 ₱313,600
   - CR Accounts Receivable 3001 ₱313,600
7. ✅ **Financial Reports → General Ledger** → AR account (3001) closing balance = ₱0

---

## Scenario 10 — Accounting: Month-End Journal Entries and Reports

> **Story:** Post month-end depreciation and verify all financial reports correctly reflect the full manufacturing cycle.

### 10.1 Post Month-End Depreciation

1. Go to **Accounting → Journal Entries → New**
2. Fill in:
   - **Date:** 2026-03-31
   - **Description:** Monthly depreciation — Injection Moulding Machine #1
3. Add two lines:
   - Line 1: **Account:** Depreciation Expense — Machinery · **Debit:** 12,500 · **Credit:** 0
   - Line 2: **Account:** Accumulated Depreciation — Machinery · **Debit:** 0 · **Credit:** 12,500
4. ✅ Debit total = Credit total = ₱12,500 (JE is balanced)
5. Click **Save as Draft** → **Submit** → **Post**
6. ✅ JE status: `posted`; visible in GL for account 6001 (or depreciation expense account), March 2026

### 10.2 Verify Trial Balance

1. Go to **Accounting → Trial Balance**
2. Select period: **March 2026**
3. Click **Generate** or **View**
4. ✅ Total Debits = Total Credits (always balanced)
5. Verify the following key accounts:

   | Account | Expected Balance |
   |---------|-----------------|
   | 2001 — Accounts Payable | ₱0 (two invoices paid in Scenario 3) |
   | 3001 — Accounts Receivable | ₱0 (invoice collected in Scenario 9) |
   | 4001 — Sales Revenue | ₱280,000 |
   | Output VAT Payable | ₱33,600 |
   | Cash / Bank (1001) | Net of opening balance, payments made, and collections received |

### 10.3 General Ledger Drilldown

1. Go to **Financial Reports → General Ledger**
2. **Account:** `2001 — Accounts Payable` · **From:** 2026-03-01 · **To:** 2026-03-31 → **Run Report**
3. ✅ Credit entries: from AP invoice approvals (Scenario 3)
4. ✅ Debit entries: from AP payments (Scenario 3)
5. ✅ Closing balance: ₱0
6. Change **Account:** to `3001 — Accounts Receivable` → **Run Report**
7. ✅ Debit entry: from AR invoice approval (Scenario 9)
8. ✅ Credit entry: from customer payment (Scenario 9)
9. ✅ Closing balance: ₱0

### 10.4 Income Statement

1. Go to **Accounting → Income Statement**
2. Select period: **March 2026** → click **Generate**
3. ✅ Revenue section: ₱280,000 (Ace Hardware net sales)
4. ✅ Expense section: includes materials expense (~₱90,000) and depreciation (₱12,500)
5. ✅ Gross profit and operating profit lines populated

### 10.5 Balance Sheet

1. Go to **Accounting → Balance Sheet**
2. **As of:** 2026-03-31 → click **Generate**
3. ✅ Total Assets = Total Liabilities + Equity
4. ✅ Output VAT Payable ₱33,600 listed under current liabilities
5. ✅ Accumulated Depreciation offset visible against machinery asset

### 10.6 Cash Flow Statement

1. Go to **Accounting → Cash Flow** (or **Financial Reports → Cash Flow**)
2. Select period: **March 2026** → click **Generate**
3. ✅ Operating activities: AP payments (outflow ~₱90,000), AR collection (inflow ₱313,600)
4. ✅ Net change in cash matches the opening/closing cash balance difference

---

## Scenario 11 — Tax: VAT Ledger and Period Closure

> **Story:** After the AR invoice approval posts Output VAT and the AP payments have been processed, finance closes the March 2026 VAT period. The closure reclassifies net payable VAT into the BIR remittance account.

### 11.1 Review the VAT Ledger for March 2026

1. Go to **Tax → VAT Ledger**
2. Filter by **Period:** March 2026
3. Verify the entries:
   - ✅ **Output VAT:** ₱33,600 — from AR invoice approval (Scenario 9)
   - ✅ **Input VAT:** any amounts from AP invoice approvals where VAT was entered (Scenario 3)
4. ✅ **Net VAT** (Output − Input) shown — this is the amount to be remitted to BIR

### 11.2 Close the March 2026 VAT Period

1. Open the March 2026 VAT Ledger entry
2. Review the summary — confirm Net VAT is positive (Output VAT > Input VAT)
3. Click **Close Period**
4. ✅ If net VAT > 0: a GL journal entry is auto-posted:
   - DR Output VAT Payable (2105) — reclassifies stored VAT
   - CR BIR VAT Remittable (2106) — amount is now in the remittance holding account
5. ✅ VAT Ledger entry status updates to `closed`
6. ✅ **Accounting → Journal Entries:** the auto-reclassification JE is visible, posted and sourced from `VatLedger`

> **If net VAT is negative** (Input VAT > Output VAT): the excess input VAT is carried forward to the April 2026 period's `carry_forward_from_prior` — no GL entry is posted. Continue to step 11.3 nonetheless.

### 11.3 Verify the Reclassification (optional drilldown)

1. Go to **Financial Reports → General Ledger**
2. **Account:** Output VAT Payable (2105) → **Run Report** → ✅ balance = ₱0 after closure
3. **Account:** BIR VAT Remittable (2106) → **Run Report** → ✅ balance = ₱33,600 (pending BIR remittance)

---

## Scenario 12 — ISO / IATF: Document Control and Internal Audit

> **Story:** Following the NCR in Scenario 7, the ISO team updates the incoming inspection procedure and conducts an internal audit on the Production department to close out the quality cycle.

### 12.1 Create and Approve a Controlled Document

1. Go to **ISO / IATF → Documents → New**
2. Fill in:
   - **Title:** Incoming Material Inspection Procedure Rev. 3 *(required)*
   - **Document Type:** Procedure *(dropdown: procedure / work_instruction / form / manual / policy / record)*
   - **Category:** Quality *(optional)*
   - **Version:** 3.0 *(optional)*
   - **Owner:** *(optional — select responsible employee)*
   - **Effective Date:** 2026-03-25
   - **Review Date:** 2027-03-25 *(optional)*
3. Click **Save** — status: `draft`; system-generated document code assigned
4. Click **Under Review** — status: `under_review`
5. Click **Approve** — status: `approved`
6. ✅ Document status: `approved`; document code visible in title; revision history updated

> **Note:** There is no manual Document Number field. The system auto-generates the code.

### 12.2 Plan and Conduct an Internal Audit

1. Go to **ISO / IATF → Internal Audits → New**
2. Fill in:
   - **Standard:** ISO 9001:2015 *(text input)*
   - **Audit Scope:** Production Department — Process Compliance (Clauses 8.5.1, 8.5.2, 8.6)
   - **Lead Auditor:** *(optional — select employee)*
   - **Audit Date:** 2026-03-28
3. Click **Save** — status: `planned`
4. Click **Start Audit** — status: `in_progress`
5. Click **Complete** — status: `completed`
6. ✅ Audit status: `completed`; reference generated

### 12.3 Record an Audit Finding and Complete the Auto-CAPA

1. On the `completed` audit, click **Add Finding**
2. Fill in:
   - **Clause:** 8.5.1
   - **Description:** Labelling SOP not followed for 3 of 10 sampled containers on Production Line 1.
   - **Severity:** Minor
3. Click **Save Finding**
4. ✅ Finding recorded; a CAPA is **auto-created** *(triggered by AuditFindingCreated event — requires queue worker)*
5. Go to **QC / QA → CAPA** — find the auto-created CAPA linked to this audit finding (type: corrective, status: open)
6. Click the **Complete** button on the CAPA entry
7. Enter resolution notes: *SOP re-briefed to all Production Line 1 operators. Labelling station daily checklist implemented.*
8. ✅ CAPA status: `completed`

### 12.4 Close the Audit Finding

1. Return to the **Internal Audit** → **Findings** section
2. Click **Close Finding** on the finding from step 12.3
3. ✅ Finding status: `closed`
4. ✅ Audit shows all findings resolved

---

## End-to-End Sanity Checklist

After completing all 12 scenarios, verify these summary points:

| Check | Expected |
|-------|----------|
| PP Resin Natural stock | ~300 kg (500 received − ~200 issued via MRQ) |
| Plastic Container 500ml stock | 7 pcs (10,007 produced − 10,000 delivered) |
| Accounts Payable (2001) | ₱0 — two invoices totalling ₱90,000 paid (Scenario 3) |
| Accounts Receivable (3001) | ₱0 — ₱313,600 collected (Scenario 9) |
| Sales Revenue (4001) | ₱280,000 net |
| Output VAT Payable (2105) | ₱0 after VAT period closure (Scenario 11) |
| BIR VAT Remittable (2106) | ₱33,600 (pending BIR remittance) |
| Trial Balance | Debits = Credits ✅ |
| MRQ from WO release | Status `fulfilled`; stock correctly reduced |
| Maintenance WO (IMM#1) | Status `completed`; equipment operational |
| Mold Container 500ml Cavity 4 | Shot counter 10,050 > 10,000; criticality CRITICAL; auto preventive WO created |
| NCR — Wall thickness | Status `closed`; CAPA `completed` |
| OQC inspection | Status `passed` |
| Delivery Receipt | Status `confirmed` |
| Shipment | Status `delivered` |
| VAT Ledger March 2026 | Status `closed`; GL reclassification posted |
| ISO Document | Status `approved` |
| Internal Audit | Status `completed`; finding `closed`; CAPA `completed` |

---

## Cross-Module Auto-Triggers Verification

These integrations fire automatically via queued events. Verify each one after completing the relevant scenario.

| Trigger | Where to Verify | Scenario |
|---------|----------------|---------|
| GR Confirmed → Stock increase | Inventory → Stock Balances (PP Resin +498 kg) | 1.4 |
| 3-Way Match passed → Draft AP Invoice | Accounting → AP Invoices (one draft per confirmed GR) | 1.4, 1.5 |
| AP Invoice Approved → GL posted | Financial Reports → GL — DR Expense / CR AP | 3.2, 3.4 |
| AP Payment Recorded → GL posted | Financial Reports → GL — DR AP / CR EWT+Cash | 3.3, 3.5 |
| Work Order Released → Draft MRQ | Inventory → Requisitions (MRQ in draft) | 4.4 |
| Production Order Complete → Draft outbound DR | Delivery → Delivery Receipts (draft DR linked to delivery schedule) | 4.7 |
| Production Order Complete → Stock increase (FG) | Inventory → Stock Balances (Plastic Container 500ml +10,007) | 4.7 |
| Mold shots ≥ max → Preventive WO | Maintenance → Work Orders (new WO linked to mold) | 6.2 |
| NCR Raised → Draft CAPA + NCR→capa_issued | QC/QA → NCRs → CAPA Actions section | 7.2 |
| Shipment Delivered → Draft AR Invoice | Accounting → AR Invoices (draft invoice for Ace Hardware) | 8.2 |
| AR Invoice Approved → GL posted | Financial Reports → GL — DR AR / CR Revenue / CR VAT | 9.1 |
| AR Payment Received → GL posted | Financial Reports → GL — DR Cash / CR AR | 9.2 |
| VAT Period Closed → GL reclassification posted | Accounting → Journal Entries + GL 2105/2106 | 11.2 |
| ISO Audit Finding Created → CAPA | QC/QA → CAPA (CAPA linked to finding) | 12.3 |

---

## Common Issues and Quick Fixes

| Symptom | Fix |
|---------|-----|
| PR form has no Title field | Correct — fill **Justification** (min 20 chars) and select **Urgency** |
| PR line items have no item dropdown | Correct — PR uses free-text Description. Only MRQ and PO use the item master dropdown |
| PO doesn't show Create button | PR must reach `vp_approved` status via Final Approve |
| Create PO button missing on PR | PO button appears only after VP approval; if PR is still pending, complete the approval chain first |
| GR does not link to PO | PO must be in `sent` or `partially_received` status before recording a GR |
| Receive Goods button missing on PO | PO must be in `sent` or `partially_received`; hidden for `draft`, `fully_received`, `cancelled` |
| AP invoice auto-creation not triggered | Check that GR was **Confirmed** (not just created); verify queue worker is running |
| AP invoice 409 on Head Note | Invoice must be in `pending_approval` first — click **Submit for Approval** before any other step |
| AP invoice 409 on Approve | Complete the 5-step chain in order: Submit → Head Note → Manager Check → Officer Review → Approve |
| MRQ Note/Check/Review buttons require two clicks | First click reveals a confirmation; second click (with "Confirm" prefix) executes the action |
| MRQ Fulfill button requires a location | Select Warehouse A – Rack 1 (WH-A1) from the "Issue From Location" dropdown before clicking Confirm Fulfill |
| Auto-MRQ not visible in Inventory → Requisitions | WO must have been Released (not just created). Refresh the page after clicking Release |
| Production WO Mark Complete disabled | **Log Output** must be recorded first (Qty Produced must be > 0) |
| Start Production button not visible on WO | WO must be in `released` status; Release button appears when status is `draft` |
| NCR creation rejected | NCR requires a **Linked Inspection** — create and submit results for the IPQC inspection first (step 7.1) |
| Close NCR button not visible | NCR must not already be `closed` or `voided`; the CAPA must be completed first is recommended but not technically blocking |
| Inspection Record Results button not visible | Inspection must be in `open` status — if already `passed` or `failed`, results are already recorded |
| Mold Log Shots field | The field is labeled **Shot Count** (not "Shots This Run"); the date field is **Log Date** |
| Mold preventive WO not auto-created | Verify `current_shots >= max_shots` after saving the shot log; check queue worker is running |
| Auto-created AR invoice has ₱0 subtotal | Delivery Schedule was created without a **Unit Price**. Edit the draft invoice and set Subtotal manually before approving |
| Auto-created AR invoice not found | Check that shipment reached `delivered` status; verify queue worker is running after marking delivered |
| Auto-created CAPA has placeholder description | Expected behavior — update the description with the actual corrective action before marking Complete |
| CAPA Complete button not visible on NCR page | CAPA must have status `open`; already `completed` CAPAs don't show the button |
| AR Invoice shows no line items | Correct — AR invoices record a **Subtotal** amount (not line items). Enter the net total; VAT is auto-computed |
| ISO Document Number not editable | Document number is system-generated — no manual input field exists |
| ISO Audit Clauses field not found | Include clause references in the **Audit Scope** text field (e.g., "Clauses 8.5.1, 8.5.2") |
| VAT Ledger Close Period button absent | Check that the VAT Ledger entry for March 2026 exists and is not already closed |
| Trial Balance debits ≠ credits | Check **Accounting → Journal Entries** for entries stuck in `draft` or `submitted` — Post them first |
| Output VAT not posted on AR | AR Invoice must reach `approved` status; approval triggers the GL posting |
| GL entries show for wrong period | Verify the **Fiscal Period** field on each invoice is set to **March 2026** |
