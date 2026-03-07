# Ogami ERP — Superadmin Testing Guide

> **Account:** `superadmin@ogamierp.local` / `SuperAdmin@12345!`
> **Environment:** `http://localhost:5173`
> **Before starting:** `php artisan migrate:fresh --seed` then `npm run dev`

This guide walks through **real-life business operations** using the superadmin account, which bypasses all role restrictions, SoD rules, and department scoping.

Scenarios follow a single **end-to-end production cycle**: raw materials in → production → quality check → ship to customer → close the books. Run them **in order** — each scenario produces data consumed by the next.

---

## Quick Reference — Seeded Master Data

| Type | Code / Name | Notes |
|------|-------------|-------|
| Raw Material | `RAW-001` — PP Resin Natural | Primary resin |
| Raw Material | `RAW-002` — HDPE Resin Black | Secondary resin |
| Finished Good | `FGD-001` — Plastic Container 500ml | Saleable item |
| Vendor | Chinatown Resins Inc. | Pre-accredited |
| Customer | Ace Hardware Philippines | Active customer |
| Equipment | Injection Moulding Machine #1 | Active |
| Mold | Container 500ml – Cavity 4 | CRITICAL (91% shot life) |
| Vehicle | TRUCK-001 — Delivery Truck 1 | Active |
| Vehicle | VAN-001 — Mitsubishi L300 Van | Active |

---

## Scenario 1 — Procurement: Raw Material Purchase

> **Story:** The warehouse is low on PP Resin (`RAW-001`). The PPC Head raises a purchase request for 500 kg. The request climbs the full approval chain before becoming a Purchase Order.

### 1.1 Create a Purchase Request

1. Go to **Procurement → Purchase Requests → New**
2. Fill in:
   - **Title:** PP Resin Replenishment — March 2026
   - **Department:** Production
   - **Required Date:** 2026-03-18
3. Add a line item:
   - **Item:** PP Resin Natural (`RAW-001`)
   - **Qty:** 500 kg, **Unit Cost:** ₱180.00 → **Line Total:** ₱90,000
4. Click **Submit**
5. ✅ Expect: Status `submitted`; PR reference number generated (e.g., `PR-2026-03-00001`)

### 1.2 Push PR Through the Approval Chain

> All five steps can be done by superadmin consecutively.

1. **Head Note** — open the PR → click **Note**, remark: *Stock below reorder level*
2. **Manager Check** — click **Check**, remark: *Approved for purchase*
3. **Purchasing Officer Review** — click **Review**; set preferred vendor: **Chinatown Resins Inc.**
4. **VP Approve** — click **VP Approve**
5. ✅ Expect: Status `vp_approved`; PR is now convertible to a Purchase Order

### 1.3 Convert to Purchase Order

1. Open the approved PR → click **Create Purchase Order**
2. Confirm:
   - **Vendor:** Chinatown Resins Inc.
   - **Expected Delivery Date:** 2026-03-18
3. Click **Issue PO**
4. ✅ Expect: Status `issued`; PO reference generated (e.g., `PO-2026-03-00001`)

### 1.4 Record a Goods Receipt (Short Delivery)

> The vendor delivers — but 2 kg are missing.

1. Go to **Procurement → Goods Receipts → New**
2. Select **PO:** `PO-2026-03-00001`
3. Enter received qty: **498 kg** (2 kg short of 500)
4. Set condition: **Accepted**, received date: 2026-03-18
5. Click **Save**
6. ✅ Expect: GR created; 3-way match shows PO qty 500 vs received 498 — short delivery flagged; RAW-001 stock increases by 498 kg

---

## Scenario 2 — Inventory: Stock Verification and Material Requisition

> **Story:** After goods receipt, the warehouse verifies on-hand balances. Production then requests 200 kg of RAW-001 to start the manufacturing run.

### 2.1 Verify Stock Balance

1. Go to **Inventory → Stock Balances**
2. Search for **RAW-001**
3. ✅ Expect: On-hand qty ≈ 498 kg (from GR in Scenario 1)

### 2.2 Create a Material Requisition

1. Go to **Inventory → Requisitions → New**
2. Fill in:
   - **Department:** Production
   - **Required Date:** 2026-03-19
   - **Purpose:** March production run for FGD-001
3. Add line: **RAW-001**, Qty: **200 kg**
4. Click **Submit**
5. ✅ Expect: Status `submitted`; MRQ reference generated

### 2.3 Push MRQ Approval and Fulfill

1. **Head Note → Manager Check → Purchasing Review → VP Approve** (same pattern as Scenario 1)
2. After VP approval, click **Fulfill Requisition**
3. ✅ Expect: Status `fulfilled`; RAW-001 stock reduced by 200 kg → ~298 kg remaining

---

## Scenario 3 — Production: Work Order Execution

> **Story:** Production starts a run of 1,000 units of Plastic Container 500ml (`FGD-001`) to fill a customer order for Ace Hardware Philippines.

### 3.1 Review the Bill of Materials

1. Go to **Production → Bill of Materials**
2. Open the BOM for **FGD-001 Plastic Container 500ml**
3. ✅ Expect: Component list showing RAW-001 and RAW-002 with quantities per unit; routing steps defined

### 3.2 Create a Delivery Schedule Entry

1. Go to **Production → Delivery Schedules → New**
2. Fill in:
   - **Customer:** Ace Hardware Philippines
   - **Product:** FGD-001 Plastic Container 500ml
   - **Scheduled Qty:** 1,000
   - **Delivery Date:** 2026-03-28
3. Click **Save**
4. ✅ Expect: Entry visible on delivery schedule board; status `scheduled`

### 3.3 Create and Release a Work Order

1. Go to **Production → Work Orders → New**
2. Fill in:
   - **Product:** FGD-001 Plastic Container 500ml
   - **Target Qty:** 1,000 units
   - **Start Date:** 2026-03-19
   - **End Date:** 2026-03-20
3. Click **Save**, then click **Release**
4. ✅ Expect: Status `in_progress`; required components reserved from inventory

### 3.4 Log Production Output

1. Open the released work order → click **Log Output**
2. Enter:
   - **Qty Produced:** 1,000
   - **Qty Rejected:** 3
3. Click **Complete Work Order**
4. ✅ Expect: Status `completed`; FGD-001 finished goods stock increases by **997 units** (1,000 − 3 rejected)

---

## Scenario 4 — QC / QA: Incoming Material Inspection and NCR

> **Story:** QC performs incoming inspection on the 498 kg of PP Resin received. 5 kg fail color deviation. A Non-Conformance Report is raised.

### 4.1 Create an Incoming Inspection

1. Go to **QC / QA → Inspections → New**
2. Fill in:
   - **Reference:** GR for RAW-001 (Scenario 1.4)
   - **Inspection Type:** Incoming Material
   - **Inspector:** Superadmin
3. Add inspection line:
   - **Item:** RAW-001, **Sample Qty:** 20 kg
   - **Result:** **Fail** — color deviation observed
4. Click **Submit**
5. ✅ Expect: Inspection status `completed`; fail result recorded against the GR

### 4.2 Raise a Non-Conformance Report (NCR)

1. Go to **QC / QA → NCR → New**
2. Fill in:
   - **Title:** PP Resin Color Deviation — Batch March 2026
   - **Category:** Incoming Material
   - **Description:** 5 kg of RAW-001 batch out-of-spec. Color deviation exceeds tolerance. Supplier informed.
   - **Root Cause:** Supplier batch contamination during mixing
   - **Corrective Action:** Return non-conforming qty to supplier; require Certificate of Analysis (COA) on all future deliveries
3. Click **Submit**
4. Enter resolution notes → click **Close NCR**
5. ✅ Expect: NCR status `closed`; CAPA entry recorded

---

## Scenario 5 — Maintenance: Preventive Work Order

> **Story:** Injection Moulding Machine #1 is due for its scheduled oil change before the next production run.

### 5.1 Create and Execute the Maintenance Work Order

1. Go to **Maintenance → Work Orders → New**
2. Fill in:
   - **Equipment:** Injection Moulding Machine #1
   - **Type:** Preventive
   - **Description:** Scheduled oil change, lubrication of moving parts, filter check
   - **Scheduled Date:** 2026-03-21
3. Click **Save**
4. Click **Start** → enter technician notes → click **Complete**
5. ✅ Expect: Work order status `completed`; equipment last-serviced date updated to 2026-03-21

---

## Scenario 6 — Mold: Shot Log and Criticality Monitor

> **Story:** The Container 500ml mold is already at 91% of its expected shot life. After the production run in Scenario 3, log the additional shots and confirm the criticality alert escalates.

### 6.1 View Current Mold Status

1. Go to **Mold** (main mold list)
2. Locate **Container 500ml – Cavity 4**
3. ✅ Expect: Criticality badge shows **CRITICAL** (≥90% shot utilization); replacement advisory visible

### 6.2 Log Production Shots

1. Open the mold record → click **Log Shots**
2. Enter:
   - **Shots This Run:** 500
   - **Production Date:** 2026-03-19
3. Click **Save**
4. ✅ Expect: Total shots counter updates; criticality badge remains or escalates; alert that mold is approaching end of life

---

## Scenario 7 — Delivery: Outbound Shipment to Customer

> **Story:** The 997 good units of FGD-001 are ready for delivery to Ace Hardware Philippines on the scheduled date.

### 7.1 Create a Delivery Receipt

1. Go to **Delivery → New Delivery Receipt**
2. Fill in:
   - **Customer:** Ace Hardware Philippines
   - **Delivery Date:** 2026-03-28
   - **Vehicle:** TRUCK-001 — Delivery Truck 1
3. Add line item:
   - **Item:** FGD-001 Plastic Container 500ml, **Qty:** 997
4. Click **Save / Deliver**
5. ✅ Expect: Delivery receipt status `delivered`; FGD-001 finished goods stock deducted by 997

---

## Scenario 8 — Accounts Payable: Vendor Invoice and Payment

> **Story:** Chinatown Resins Inc. sends its invoice for the 498 kg delivered at ₱180/kg = ₱89,640.

### 8.1 Create and Approve the Vendor Invoice

1. Go to **Accounting → AP Invoices → New**
2. Fill in:
   - **Vendor:** Chinatown Resins Inc.
   - **Invoice No:** `CR-INV-2026-0318`
   - **Invoice Date:** 2026-03-18
   - **Due Date:** 2026-04-17
3. Add line:
   - **Description:** PP Resin Natural, **Qty:** 498, **Unit Price:** ₱180.00 → **Total:** ₱89,640
4. Click **Submit** → **Approve**
5. ✅ Expect: Journal Entry auto-posted — Inventory ↑ ₱89,640 / Accounts Payable ↑ ₱89,640

### 8.2 Record Vendor Payment

1. Open the approved invoice → click **Record Payment**
2. Fill in:
   - **Payment Date:** 2026-03-25
   - **Amount:** ₱89,640
   - **Bank Account:** (select seeded cash account)
3. Click **Save**
4. ✅ Expect: Invoice status `paid`; GL entry: Accounts Payable ↓ ₱89,640 / Cash ↓ ₱89,640; AP balance = ₱0

---

## Scenario 9 — Accounts Receivable: Customer Invoice and Collection

> **Story:** Bill Ace Hardware Philippines for the 997 units delivered at ₱250/unit = ₱249,250.

### 9.1 Create and Approve the Customer Invoice

1. Go to **Accounting → AR Invoices → New**
2. Fill in:
   - **Customer:** Ace Hardware Philippines
   - **Invoice No:** `OG-AR-2026-0301`
   - **Invoice Date:** 2026-03-28
   - **Due Date:** 2026-04-28
3. Add line:
   - **Item:** FGD-001 Plastic Container 500ml, **Qty:** 997, **Unit Price:** ₱250.00 → **Total:** ₱249,250
4. Click **Approve**
5. ✅ Expect: GL entry: Accounts Receivable ↑ ₱249,250 / Sales Revenue ↑ ₱249,250

### 9.2 Receive Customer Payment

1. Open the approved invoice → click **Receive Payment**
2. Fill in:
   - **Payment Date:** 2026-04-05
   - **Amount:** ₱249,250
3. Click **Save**
4. ✅ Expect: Invoice status `paid`; GL entry: Cash ↑ ₱249,250 / Accounts Receivable ↓ ₱249,250; AR balance = ₱0

---

## Scenario 10 — Accounting: Close and Verify the Books

> **Story:** With all transactions posted, verify the financials balance and the reports reflect the cycle correctly.

### 10.1 Trial Balance

1. Go to **Accounting → Trial Balance**
2. Select period: **March 2026**
3. ✅ Expect: Total Debits = Total Credits; key accounts present:
   - Inventory — reflects GR receipt and MRQ issue
   - Accounts Payable — ₱0 (paid in Scenario 8)
   - Accounts Receivable — ₱0 (collected in Scenario 9)
   - Sales Revenue — ₱249,250
   - Cash — net of payments and collections

### 10.2 General Ledger — AP Account Detail

1. Go to **Accounting → General Ledger**
2. Filter by account: **Accounts Payable**
3. ✅ Expect: Two entries visible — invoice post (credit) and payment (debit); ending balance ₱0

### 10.3 General Ledger — AR Account Detail

1. Filter by account: **Accounts Receivable**
2. ✅ Expect: Invoice entry (debit) and collection (credit); ending balance ₱0

### 10.4 Income Statement

1. Go to **Accounting → Income Statement**
2. Select period: **March 2026**
3. ✅ Expect: Sales Revenue ₱249,250 visible under revenue; gross profit line populated

---

## Scenario 11 — ISO / IATF: Document Control and Audit

> **Story:** Following the NCR in Scenario 4, the ISO team updates the incoming inspection procedure and runs an internal audit on the Production department.

### 11.1 Upload an Updated Controlled Document

1. Go to **ISO / IATF → Documents → New**
2. Fill in:
   - **Title:** Incoming Material Inspection Procedure Rev. 3
   - **Document No:** `QP-INC-003`
   - **Type:** Quality Procedure
   - **Revision:** 3
   - **Effective Date:** 2026-03-25
3. Upload a PDF file
4. Click **Submit**
5. ✅ Expect: Document listed as active controlled document; revision history shows Rev. 3

### 11.2 Create an Internal Audit

1. Go to **ISO / IATF → Internal Audits → New**
2. Fill in:
   - **Scope:** Production Department — Process Compliance
   - **Audit Date:** 2026-03-28
   - **Lead Auditor:** Superadmin
3. Add a finding:
   - **Clause:** 8.5.1 — Control of production processes
   - **Finding:** Non-conformance — labelling SOP not followed for 3 of 10 sampled containers
   - **Classification:** Minor NC
4. Click **Save**
5. ✅ Expect: Audit saved with finding recorded; open finding visible in audit dashboard

---

## Scenario 12 — Administration: System Verification

> **Story:** Final check that all user accounts, roles, and audit trail reflect the day's activity.

### 12.1 View User Accounts

1. Go to **Administration → Users**
2. ✅ Expect: All seeded user accounts visible (Chairman, President, VP, all Managers, Officers, Heads)

### 12.2 Check Role and Department Assignments

1. Open the VP user (`vp@ogamierp.local`)
2. ✅ Expect: Role `vice_president`; department scope listed

### 12.3 View Audit Log

1. Go to **Administration → Audit Log**
2. ✅ Expect: Trail of all changes made during this session — PR created, PO issued, GR saved, WO completed, invoices posted, payments recorded

---

## End-to-End Sanity Checklist

After completing all scenarios, verify these summary points:

| Check | Expected Value |
|-------|----------------|
| RAW-001 stock | ~298 kg (498 received − 200 issued via MRQ) |
| FGD-001 stock | 0 units (997 produced, 997 delivered) |
| Accounts Payable balance | ₱0 (invoice of ₱89,640 paid) |
| Accounts Receivable balance | ₱0 (invoice of ₱249,250 collected) |
| Sales Revenue (March 2026) | ₱249,250 |
| Trial Balance | Debits = Credits |
| NCR — Color Deviation batch | Status `closed` |
| Mold Container 500ml | Shot counter updated; CRITICAL badge shown |
| Maintenance WO — IMM #1 | Status `completed`; last-serviced 2026-03-21 |
| Delivery Receipt | Status `delivered`; linked to Ace Hardware Philippines |

---

## Common Issues & Quick Fixes

| Symptom | Fix |
|---------|-----|
| GR does not link to PO | Ensure the PO is in `issued` status before creating the GR |
| 3-way match not triggered | Both GR qty and AP invoice qty must be saved against the same PO |
| Stock balance not updated after GR | Check that GR status is `completed`/`received`, not `draft` |
| Work order components not reserved | Ensure the BOM for FGD-001 is active and component stock is sufficient |
| Journal entry not auto-posted | AP invoice or AR invoice must reach `approved` status — check status before looking at GL |
| Delivery stock not deducted | Delivery receipt must reach `delivered` status |
| Trial balance debits ≠ credits | Look for unposted journal entries in draft status under **Accounting → Journal Entries** |
| ISO document not visible | Check that effective date is set and document status is `active` |

