# Production and Supply Chain Role-Based Testing Guide

Date: 2026-04-04
Login URL: http://localhost:5173/login

## Objective

Run a full role-based end-to-end manual test across demand, procurement, receiving, QC, production, delivery, AP, and AR with clear ownership per role.

## Pre-Test Setup

1. Reset and seed data:
	- `php artisan migrate:fresh --seed`
2. Start services:
	- `npm run dev`
3. Confirm login page is accessible on `http://localhost:5173/login`.

## Test Accounts to Use

| Role | Email | Password |
|---|---|---|
| Purchasing Officer | purchasing.officer@ogamierp.local | Officer@12345! |
| Warehouse Head | warehouse.head@ogamierp.local | Head@123456789! |
| Production Manager | prod.manager@ogamierp.local | Manager@Test1234! |
| Production Head | production.head@ogamierp.local | Head@123456789! |
| QC Officer | qc.officer@ogamierp.local | Officer@Test1234! |
| QC Manager | qc.manager@ogamierp.local | Manager@Test1234! |
| Accounting Officer | accounting@ogamierp.local | Officer@12345! |
| Accounting Manager | acctg.manager@ogamierp.local | Manager@Test1234! |
| VP | vp@ogamierp.local | Vice_president@Test1234! |
| Sales Officer | sales.officer@ogamierp.local | Officer@Test1234! |
| Sales Manager | sales.manager@ogamierp.local | Manager@Test1234! |

## End-to-End Baton Workflow

Start chain used in this guide:

0. Customer creates demand (client portal)
1. Sales review/confirmation
2. Production planning
3. Procurement for shortages
4. Receiving and inbound QC
5. AP posting and payment
6. Production execution and outgoing QC
7. Delivery to customer
8. AR invoice and collection

Definition:
- Customer demand means the initial customer-side requirement that starts the chain, typically a client order, sales order, or delivery schedule request containing item, quantity, and required date.

Process rule:
- Preferred process: demand starts from the client portal (customer order).
- Sales-created demand is only a fallback path for offline/manual channels (phone/email/walk-in orders).

### Phase 0: Client-Originated Demand (Preferred Start)

0. Customer submits order from client portal.
	- Account: client@ogami.test / Portal@Test1234!
	- Action: Create client order with item, quantity, and required delivery date.
	- Expected: status starts as new/submitted and appears in Sales queue.

### Phase A: Customer Demand and Sales Confirmation (Top of Chain)

1. Create customer demand document.
	- Account: sales.officer@ogamierp.local / Officer@Test1234!
	- Action: Review the client-originated order, validate details, and update required commercial fields.
	- Note: Do not use this step to originate demand unless using offline/manual fallback.
	- Expected: status starts as draft/submitted.

2. Approve/confirm sales document.
	- Account: sales.manager@ogamierp.local / Manager@Test1234!
	- Action: Approve or confirm the sales-side document.
	- Expected: status moves to approved/confirmed and becomes production-eligible.

### Phase B: Production Planning and Material Requirement

3. Create and release production order from approved demand.
	- Account: prod.manager@ogamierp.local / Manager@Test1234!
	- Action: Create production order and release it.
	- Expected: production order reaches released status.
	- Expected: material requirement/requisition is generated or missing materials are identified.

### Phase C: Procurement for Raw Material Shortage

4. Create purchase request for shortages.
	- Account: purchasing.officer@ogamierp.local / Officer@12345!
	- Action: Create PR for missing raw materials and submit PR.

5. Approve PR until PO creation is enabled.
	- Account (final approval): vp@ogamierp.local / Vice_president@Test1234!
	- Action: Complete approval chain through required approvers.
	- Expected: PO creation action is available.

6. Create PO and send to vendor.
	- Account: purchasing.officer@ogamierp.local / Officer@12345!
	- Action: Create PO from approved PR and send to vendor.
	- Expected: PO status becomes sent/acknowledged.

### Phase D: Receiving and Inbound QC Gate

7. Create GR from PO and submit for QC.
	- Account: warehouse.head@ogamierp.local / Head@123456789!
	- Action: Create goods receipt and submit for QC.
	- Expected: GR status becomes pending_qc.

8. Perform incoming QC inspection.
	- Account (option A): qc.officer@ogamierp.local / Officer@Test1234!
	- Account (option B): qc.manager@ogamierp.local / Manager@Test1234!
	- Pass path action: mark as QC passed.
	- Pass path expected: GR confirmation is allowed.
	- Fail path action: mark as QC failed.
	- Fail path expected: GR confirmation is blocked and NCR/CAPA path is available (if configured).

9. Confirm GR after QC pass.
	- Account: warehouse.head@ogamierp.local / Head@123456789!
	- Action: Confirm GR only after pass.
	- Expected: stock increases.
	- Expected: PO moves to partially_received or fully_received.

### Phase E: AP (Procure-to-Pay)

10. Review AP invoice generated from GR/3-way process.
	- Account: accounting@ogamierp.local / Officer@12345!
	- Action: Open AP invoices, validate auto-draft, submit/review.

11. Approve AP and post payment.
	- Account (manager approval): acctg.manager@ogamierp.local / Manager@Test1234!
	- Account (if VP threshold applies): vp@ogamierp.local / Vice_president@Test1234!
	- Action: Complete approvals and post payment.
	- Expected: AP invoice paid/posted and journal entries generated.

### Phase F: Production Execution and Outgoing/Internal QC

12. Execute production run.
	- Account: production.head@ogamierp.local / Head@123456789!
	- Action: Start production order, log progress, complete output.
	- Expected: finished goods stock increases on completion.

13. Perform production/output QC gate.
	- Account (option A): qc.officer@ogamierp.local / Officer@Test1234!
	- Account (option B): qc.manager@ogamierp.local / Manager@Test1234!
	- Pass path expected: order/output becomes delivery-ready.
	- Fail path expected: outbound delivery is blocked until issue closure.

### Phase G: Delivery and AR (Order-to-Cash)

14. Execute delivery flow.
	- Account: warehouse.head@ogamierp.local / Head@123456789!
	- Action: Create/confirm delivery receipt, dispatch, mark delivered.
	- Expected: delivery statuses progress correctly.

15. Post AR invoice.
	- Account: accounting@ogamierp.local / Officer@12345!
	- Action: Validate AR invoice creation (auto/manual) and post invoice.

16. Record customer collection.
	- Account: acctg.manager@ogamierp.local / Manager@Test1234!
	- Action: Record customer payment.
	- Expected: AR invoice status becomes paid and accounting entries are posted.

## Why Sales Can See Delivery & Logistics

- Current UI/RBAC configuration allows SALES department visibility into delivery schedules/receipts for coordination and customer communication.
- Operational execution remains owned by Warehouse/Delivery users.
- In this test, Sales should be read/monitor-only in Delivery, while Warehouse performs dispatch/delivered actions.

## Required Negative Tests

1. Try confirming GR before QC pass.
	- Account: warehouse.head@ogamierp.local / Head@123456789!
	- Expected: blocked.

2. Try starting production with unresolved material issuance constraints.
	- Account: production.head@ogamierp.local / Head@123456789!
	- Expected: blocked unless authorized override exists.

3. Try approving a transaction by the same user who created it.
	- Account: use same creator account for the approval attempt (example: purchasing.officer@ogamierp.local / Officer@12345!).
	- Expected: SoD block where policy is enforced.

4. Try progressing delivery when production/QC is failed/held.
	- Account: warehouse.head@ogamierp.local / Head@123456789!
	- Expected: blocked.

## Evidence Capture Template

For every major transition, capture:

1. Actor account used.
2. Reference number (PO/GR/Production Order/DR/Invoice).
3. Before and after status.
4. Timestamp.
5. Screenshot.
6. Result: PASS/FAIL.
7. If FAIL: exact error message and page/API context.

## Pass Criteria

1. Unauthorized actions do not succeed.
2. Required status transitions complete without bypass.
3. QC fail blocks downstream steps.
4. Stock movements occur only via valid workflow actions.
5. AP and AR postings complete without orphan records.
