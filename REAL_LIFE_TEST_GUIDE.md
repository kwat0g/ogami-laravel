# Ogami ERP — Real-Life Scenario Test Guide

**Manufacturing Business Flow: From Setup to Delivery**

This guide walks through a complete manufacturing business cycle, testing all major modules in a realistic sequence. We'll skip HR, Leave, OT, and Self-Service modules as requested.

---

## Prerequisites

### System Setup
1. Database seeded with: `php artisan migrate:fresh --seed`
2. Frontend running: `npm run dev` (Vite on port 5173)
3. Backend running: `php artisan serve` (Laravel on port 8000)

### Test User Accounts (Seeded)

| Role | Email | Password | Department | Purpose |
|------|-------|----------|------------|---------|
| Accounting Officer | acctg.officer@ogamierp.local | AcctgManager@1234! | ACCTG | Financial management, AP/AR |
| Purchasing Officer | purchasing.officer@ogamierp.local | Officer@12345! | PROC | Procurement operations |
| Warehouse Head | warehouse.head@ogamierp.local | Head@123456789! | WH | Inventory management |
| PPC Head | ppc.head@ogamierp.local | Head@123456789! | PPC | Production planning |
| Production Manager | prod.manager@ogamierp.local | Manager@12345! | PROD | Production execution |
| QC Manager | qc.manager@ogamierp.local | Manager@12345! | QC | Quality control |
| Vice President | vp@ogamierp.local | VicePresident@1! | — | Final approvals |

---

## Phase 1: Initial System Setup

### 1.1 Setup Bank Accounts
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/banking/accounts`

1. Navigate to: `http://localhost:5173/login`
2. Login with: acctg.officer@ogamierp.local / AcctgManager@1234!
3. Click sidebar: "Banking" → "Bank Accounts"
4. Click "Add Bank Account"
5. Fill form:
   - Bank Name: BDO Unibank
   - Account Number: 001234567890
   - Account Name: Ogami Manufacturing Inc.
   - Account Type: Checking
   - Currency: PHP
   - GL Account: 1000 (Cash)
   - Opening Balance: 5,000,000.00 (₱5M)
   - Opening Date: 2026-01-01
6. Click "Save"
7. Verify bank account appears in list

### 1.2 Setup Vendors
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/accounting/vendors`

1. Click sidebar: "Accounting" → "Vendors"
2. Click "Add Vendor"
3. Fill form for Vendor #1:
   - Vendor Code: VEND-001
   - Company Name: ABC Plastics Supply Corp.
   - Contact Person: Juan Dela Cruz
   - Email: sales@abcplastics.ph
   - Phone: 02-8123-4567
   - Address: 123 Industrial Ave, Valenzuela City
   - TIN: 123-456-789-000
   - Payment Terms: Net 30
   - Credit Limit: 500,000.00
4. Click "Save"
5. Repeat for Vendor #2:
   - Vendor Code: VEND-002
   - Company Name: XYZ Chemical Trading
   - Contact Person: Maria Santos
   - Email: info@xyzchemical.ph
   - Phone: 02-8234-5678
   - Address: 456 Chemistry St, Quezon City
   - TIN: 234-567-890-001
   - Payment Terms: Net 15
   - Credit Limit: 300,000.00
6. Click "Save"

### 1.3 Setup Customers
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/ar/customers`


1. Click sidebar: "AR" → "Customers"
2. Click "Add Customer"
3. Fill form for Customer #1:
   - Customer Code: CUST-001
   - Company Name: Toyota Motor Philippines
   - Contact Person: Robert Tan
   - Email: procurement@toyota.ph
   - Phone: 02-8345-6789
   - Address: 789 Automotive Blvd, Laguna
   - TIN: 345-678-901-002
   - Payment Terms: Net 45
   - Credit Limit: 2,000,000.00
4. Click "Save"
5. Repeat for Customer #2:
   - Customer Code: CUST-002
   - Company Name: Honda Cars Philippines
   - Contact Person: Lisa Garcia
   - Email: supply@honda.ph
   - Phone: 02-8456-7890
   - Address: 321 Motor Ave, Cavite
   - TIN: 456-789-012-003
   - Payment Terms: Net 60
   - Credit Limit: 1,500,000.00
6. Click "Save"
7. Logout

---

## Phase 2: Inventory Setup

### 2.1 Setup Item Categories
**User:** warehouse.head@ogamierp.local / Head@123456789!
**Page:** `/inventory/categories`

1. Login with: warehouse.head@ogamierp.local / Head@123456789!
2. Click sidebar: "Inventory" → "Item Categories"
3. Click "Add Category"
4. Create categories:
   - Raw Materials
   - Finished Goods
   - Packaging Materials
   - Consumables
5. Click "Save" for each

### 2.2 Setup Warehouse Locations
**User:** warehouse.head@ogamierp.local / Head@123456789!
**Page:** `/inventory/locations`

1. Click sidebar: "Inventory" → "Warehouse Locations"
2. Click "Add Location"
3. Create locations:
   - WH-A1 (Raw Materials Storage)
   - WH-B1 (Finished Goods Storage)
   - WH-C1 (Packaging Storage)
   - PROD-FLOOR (Production Floor)
4. Click "Save" for each

### 2.3 Create Raw Material Items
**User:** warehouse.head@ogamierp.local / Head@123456789!
**Page:** `/inventory/items/new`

1. Click sidebar: "Inventory" → "Items"
2. Click "Add Item"
3. Fill form for Item #1:
   - Item Code: RM-PP-001
   - Item Name: PP Resin (Polypropylene)
   - Category: Raw Materials
   - Unit of Measure: KG
   - Reorder Point: 500
   - Reorder Quantity: 1000
   - Unit Cost: 85.00
   - Location: WH-A1
4. Click "Save"
5. Repeat for Item #2:
   - Item Code: RM-ABS-001
   - Item Name: ABS Resin
   - Category: Raw Materials
   - Unit of Measure: KG
   - Reorder Point: 300
   - Reorder Quantity: 500
   - Unit Cost: 120.00
   - Location: WH-A1
6. Repeat for Item #3:
   - Item Code: PKG-BOX-001
   - Item Name: Corrugated Box (Large)
   - Category: Packaging Materials
   - Unit of Measure: PCS
   - Reorder Point: 100
   - Reorder Quantity: 500
   - Unit Cost: 25.00
   - Location: WH-C1
7. Click "Save" for each


### 2.4 Create Finished Goods Items
**User:** warehouse.head@ogamierp.local / Head@123456789!
**Page:** `/inventory/items/new`

1. Click "Add Item"
2. Fill form for Finished Good #1:
   - Item Code: FG-DASH-001
   - Item Name: Dashboard Panel Assembly
   - Category: Finished Goods
   - Unit of Measure: PCS
   - Reorder Point: 50
   - Reorder Quantity: 100
   - Unit Cost: 850.00 (will be calculated from BOM)
   - Location: WH-B1
3. Click "Save"
4. Repeat for Finished Good #2:
   - Item Code: FG-TRIM-001
   - Item Name: Door Trim Panel
   - Category: Finished Goods
   - Unit of Measure: PCS
   - Reorder Point: 100
   - Reorder Quantity: 200
   - Unit Cost: 450.00
   - Location: WH-B1
5. Click "Save"
6. Logout

---

## Phase 3: Procurement Process

### 3.1 Create Purchase Request
**User:** purchasing.officer@ogamierp.local / Officer@12345!
**Page:** `/procurement/purchase-requests/new`

1. Logout from warehouse head
2. Login with: purchasing.officer@ogamierp.local / Officer@12345!
3. Click sidebar: "Procurement" → "Purchase Requests"
4. Click "New Purchase Request"
5. Fill form:
   - PR Number: (auto-generated)
   - Requested By: (current user)
   - Department: PROC
   - Request Date: 2026-03-14
   - Required Date: 2026-03-20
   - Purpose: Raw materials for March production
6. Add line items:
   - Item: RM-PP-001 (PP Resin)
   - Quantity: 1000 KG
   - Unit Price: 85.00
   - Total: 85,000.00
7. Click "Add Item"
8. Add second item:
   - Item: RM-ABS-001 (ABS Resin)
   - Quantity: 500 KG
   - Unit Price: 120.00
   - Total: 60,000.00
9. Click "Add Item"
10. Verify Total: ₱145,000.00
11. Click "Submit for Approval"
12. Verify status changes to "Pending Approval"
13. Logout

### 3.2 Approve Purchase Request (Accounting)
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/procurement/purchase-requests`

1. Login with: acctg.officer@ogamierp.local / AcctgManager@1234!
2. Click sidebar: "Procurement" → "Purchase Requests"
3. Find the PR created in step 3.1
4. Click on PR number to view details
5. Click "Approve" button
6. Add remarks: "Approved for March production schedule"
7. Click "Confirm Approval"
8. Verify status changes to "Approved"
9. Logout

### 3.3 Create Purchase Order from PR
**User:** purchasing.officer@ogamierp.local / Officer@12345!
**Page:** `/procurement/purchase-orders/new`

1. Login with: purchasing.officer@ogamierp.local / Officer@12345!
2. Click sidebar: "Procurement" → "Purchase Orders"
3. Click "New Purchase Order"
4. Select "Create from PR"
5. Select the approved PR from step 3.2
6. Fill form:
   - PO Number: (auto-generated)
   - Vendor: VEND-001 (ABC Plastics Supply Corp.)
   - Order Date: 2026-03-14
   - Expected Delivery: 2026-03-20
   - Payment Terms: Net 30
7. Verify line items are copied from PR
8. Click "Create Purchase Order"
9. Verify PO is created with status "Draft"
10. Click "Send to Vendor"
11. Verify status changes to "Sent"
12. Logout


### 3.4 Create Goods Receipt
**User:** warehouse.head@ogamierp.local / Head@123456789!
**Page:** `/procurement/goods-receipts/new`

1. Logout from purchasing officer
2. Login with: warehouse.head@ogamierp.local / Head@123456789!
3. Click sidebar: "Procurement" → "Goods Receipts"
4. Click "New Goods Receipt"
5. Fill form:
   - GR Number: (auto-generated)
   - Purchase Order: Select PO from step 3.3
   - Receipt Date: 2026-03-18
   - Received By: (current user)
   - Warehouse: WH-A1
6. Verify line items from PO:
   - RM-PP-001: 1000 KG
   - RM-ABS-001: 500 KG
7. Update received quantities (simulate partial delivery):
   - RM-PP-001: 1000 KG (full)
   - RM-ABS-001: 400 KG (partial - 100 KG short)
8. Add remarks: "ABS Resin short by 100 KG - vendor to deliver balance next week"
9. Click "Create Goods Receipt"
10. Verify GR is created
11. Click "Post to Inventory"
12. Verify stock levels updated in inventory

### 3.5 Verify Stock Balance
**User:** warehouse.head@ogamierp.local / Head@123456789!
**Page:** `/inventory/stock`

1. Click sidebar: "Inventory" → "Stock Balance"
2. Search for "RM-PP-001"
3. Verify quantity: 1000 KG at WH-A1
4. Search for "RM-ABS-001"
5. Verify quantity: 400 KG at WH-A1
6. Click on item to view stock ledger
7. Verify transaction: GR posted with correct quantities
8. Logout

---

## Phase 4: Accounts Payable

### 4.1 Create Vendor Invoice
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/accounting/ap/invoices/new`

1. Login with: acctg.officer@ogamierp.local / AcctgManager@1234!
2. Click sidebar: "Accounting" → "AP Invoices"
3. Click "New Invoice"
4. Fill form:
   - Invoice Number: INV-ABC-2026-001
   - Vendor: VEND-001 (ABC Plastics Supply Corp.)
   - Invoice Date: 2026-03-18
   - Due Date: 2026-04-17 (Net 30)
   - Goods Receipt: Select GR from step 3.4
5. Verify line items from GR:
   - RM-PP-001: 1000 KG × ₱85.00 = ₱85,000.00
   - RM-ABS-001: 400 KG × ₱120.00 = ₱48,000.00
6. Add VAT (12%):
   - Subtotal: ₱133,000.00
   - VAT: ₱15,960.00
   - Total: ₱148,960.00
7. Click "Create Invoice"
8. Verify invoice status: "Pending Approval"

### 4.2 Approve Vendor Invoice (VP Approval)
**User:** vp@ogamierp.local / VicePresident@1!
**Page:** `/accounting/ap/invoices`

1. Logout from accounting officer
2. Login with: vp@ogamierp.local / VicePresident@1!
3. Click sidebar: "Accounting" → "AP Invoices"
4. Find invoice INV-ABC-2026-001
5. Click on invoice number
6. Click "Approve"
7. Add remarks: "Approved for payment"
8. Click "Confirm Approval"
9. Verify status changes to "Approved"
10. Logout

### 4.3 Post Vendor Invoice to GL
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/accounting/ap/invoices`

1. Login with: acctg.officer@ogamierp.local / AcctgManager@1234!
2. Find invoice INV-ABC-2026-001
3. Click on invoice number
4. Verify GL entries preview:
   - DR: Inventory (5000) ₱133,000.00
   - DR: Input VAT (1200) ₱15,960.00
   - CR: Accounts Payable (2000) ₱148,960.00
5. Click "Post to GL"
6. Verify status changes to "Posted"
7. Verify journal entry created
8. Logout

---

## Phase 5: Production Planning

### 5.1 Create Bill of Materials (BOM)
**User:** ppc.head@ogamierp.local / Head@123456789!
**Page:** `/production/boms/new`

1. Login with: ppc.head@ogamierp.local / Head@123456789!
2. Click sidebar: "Production" → "BOMs"
3. Click "New BOM"
4. Fill form:
   - BOM Code: BOM-DASH-001
   - Product: FG-DASH-001 (Dashboard Panel Assembly)
   - Version: 1.0
   - Effective Date: 2026-03-01
   - Status: Active
5. Add components:
   - Component 1:
     * Item: RM-PP-001 (PP Resin)
     * Quantity: 2.5 KG
     * Scrap %: 5%
   - Component 2:
     * Item: RM-ABS-001 (ABS Resin)
     * Quantity: 1.0 KG
     * Scrap %: 3%
   - Component 3:
     * Item: PKG-BOX-001 (Corrugated Box)
     * Quantity: 1 PCS
     * Scrap %: 0%
6. Click "Save BOM"
7. Verify BOM is created and active


### 5.2 Create Delivery Schedule
**User:** ppc.head@ogamierp.local / Head@123456789!
**Page:** `/production/delivery-schedules/new`

1. Click sidebar: "Production" → "Delivery Schedules"
2. Click "New Delivery Schedule"
3. Fill form:
   - Schedule Code: DS-2026-03-001
   - Customer: CUST-001 (Toyota Motor Philippines)
   - Product: FG-DASH-001 (Dashboard Panel Assembly)
   - Quantity: 200 PCS
   - Delivery Date: 2026-03-30
   - Status: Confirmed
4. Click "Save"
5. Verify delivery schedule created

### 5.3 Create Production Order
**User:** ppc.head@ogamierp.local / Head@123456789!
**Page:** `/production/orders/new`

1. Click sidebar: "Production" → "Production Orders"
2. Click "New Production Order"
3. Fill form:
   - PO Number: (auto-generated)
   - Product: FG-DASH-001 (Dashboard Panel Assembly)
   - BOM: BOM-DASH-001 v1.0
   - Quantity to Produce: 200 PCS
   - Planned Start: 2026-03-20
   - Planned End: 2026-03-28
   - Delivery Schedule: DS-2026-03-001
4. Verify material requirements calculated:
   - RM-PP-001: 525 KG (200 × 2.5 × 1.05)
   - RM-ABS-001: 206 KG (200 × 1.0 × 1.03)
   - PKG-BOX-001: 200 PCS
5. Click "Create Production Order"
6. Verify status: "Draft"

### 5.4 Approve Production Order (Production Manager)
**User:** prod.manager@ogamierp.local / Manager@12345!
**Page:** `/production/orders`

1. Logout from PPC head
2. Login with: prod.manager@ogamierp.local / Manager@12345!
3. Click sidebar: "Production" → "Production Orders"
4. Find production order from step 5.3
5. Click on PO number
6. Click "Approve"
7. Add remarks: "Approved for production"
8. Click "Confirm Approval"
9. Verify status changes to "Approved"

### 5.5 Release Production Order
**User:** prod.manager@ogamierp.local / Manager@12345!
**Page:** `/production/orders/{ulid}`

1. Click on production order
2. Click "Release to Production"
3. Verify material availability check:
   - RM-PP-001: Available 1000 KG ≥ Required 525 KG ✓
   - RM-ABS-001: Available 400 KG ≥ Required 206 KG ✓
   - PKG-BOX-001: Available 0 PCS < Required 200 PCS ✗
4. Note: Packaging material is short
5. Click "Release Anyway" (will create backorder for packaging)
6. Verify status changes to "Released"
7. Logout

---

## Phase 6: Material Requisition

### 6.1 Create Material Requisition from Production Order
**User:** warehouse.head@ogamierp.local / Head@123456789!
**Page:** `/inventory/requisitions/new`

1. Login with: warehouse.head@ogamierp.local / Head@123456789!
2. Click sidebar: "Inventory" → "Material Requisitions"
3. Click "New Requisition"
4. Fill form:
   - MR Number: (auto-generated)
   - Production Order: Select PO from step 5.5
   - Requested By: (current user)
   - Request Date: 2026-03-20
   - Required Date: 2026-03-20
5. Verify line items from production order:
   - RM-PP-001: 525 KG from WH-A1
   - RM-ABS-001: 206 KG from WH-A1
   - PKG-BOX-001: 200 PCS from WH-C1 (backorder)
6. Click "Submit Requisition"
7. Verify status: "Pending Approval"

### 6.2 Approve Material Requisition (Production Manager)
**User:** prod.manager@ogamierp.local / Manager@12345!
**Page:** `/inventory/requisitions`

1. Logout from warehouse head
2. Login with: prod.manager@ogamierp.local / Manager@12345!
3. Click sidebar: "Inventory" → "Material Requisitions"
4. Find MR from step 6.1
5. Click on MR number
6. Click "Approve"
7. Add remarks: "Approved for production"
8. Click "Confirm Approval"
9. Verify status changes to "Approved"
10. Logout

### 6.3 Issue Materials
**User:** warehouse.head@ogamierp.local / Head@123456789!
**Page:** `/inventory/requisitions`

1. Login with: warehouse.head@ogamierp.local / Head@123456789!
2. Find approved MR
3. Click on MR number
4. Click "Issue Materials"
5. Verify materials issued to production floor
6. Check stock ledger:
   - RM-PP-001: 1000 - 525 = 475 KG remaining
   - RM-ABS-001: 400 - 206 = 194 KG remaining
   - PKG-BOX-001: Backorder created
7. Logout

---

## Phase 7: Production Execution

### 7.1 Start Production
**User:** prod.manager@ogamierp.local / Manager@12345!
**Page:** `/production/orders/{ulid}`

1. Login with: prod.manager@ogamierp.local / Manager@12345!
2. Navigate to production order
3. Click "Start Production"
4. Verify status changes to "In Progress"
5. Record start time: 2026-03-20 08:00

### 7.2 Record Production Output
**User:** prod.manager@ogamierp.local / Manager@12345!
**Page:** `/production/orders/{ulid}`

1. Click "Record Output"
2. Fill form:
   - Output Date: 2026-03-25
   - Quantity Produced: 180 PCS
   - Quantity Rejected: 20 PCS
   - Rejection Reason: Surface defects
3. Click "Save Output"
4. Verify cumulative output: 180 PCS good, 20 PCS rejected
5. Logout



---

## Phase 8: Quality Control

### 8.1 Create Inspection
**User:** qc.manager@ogamierp.local / Manager@12345!
**Page:** `/qc/inspections/new`

1. Login with: qc.manager@ogamierp.local / Manager@12345!
2. Click sidebar: "QC" → "Inspections"
3. Click "New Inspection"
4. Fill form:
   - Inspection Number: (auto-generated)
   - Production Order: Select PO from Phase 7
   - Inspection Type: Final Inspection
   - Inspector: (current user)
   - Inspection Date: 2026-03-25
   - Sample Size: 20 PCS
5. Add inspection results:
   - Dimension Check: Pass (18/20)
   - Surface Quality: Pass (17/20)
   - Color Match: Pass (20/20)
   - Overall Result: Pass
6. Click "Save Inspection"
7. Verify inspection record created

### 8.2 Create NCR for Rejected Items
**User:** qc.manager@ogamierp.local / Manager@12345!
**Page:** `/qc/ncrs/new`

1. Click sidebar: "QC" → "NCRs"
2. Click "New NCR"
3. Fill form:
   - NCR Number: (auto-generated)
   - Production Order: Select PO from Phase 7
   - Defect Type: Surface Defects
   - Quantity Affected: 20 PCS
   - Severity: Minor
   - Description: Surface scratches and discoloration
   - Root Cause: Mold temperature too high
   - Corrective Action: Adjust mold temperature settings
   - Preventive Action: Add temperature monitoring to checklist
4. Click "Save NCR"
5. Verify NCR created
6. Logout

---

## Phase 9: Finished Goods Receipt

### 9.1 Complete Production Order
**User:** prod.manager@ogamierp.local / Manager@12345!
**Page:** `/production/orders/{ulid}`

1. Login with: prod.manager@ogamierp.local / Manager@12345!
2. Navigate to production order
3. Click "Complete Production"
4. Verify final quantities:
   - Good Output: 180 PCS
   - Rejected: 20 PCS
   - Total: 200 PCS
5. Click "Confirm Completion"
6. Verify status changes to "Completed"
7. Logout

### 9.2 Receive Finished Goods to Warehouse
**User:** warehouse.head@ogamierp.local / Head@123456789!
**Page:** `/inventory/stock`

1. Login with: warehouse.head@ogamierp.local / Head@123456789!
2. Click sidebar: "Inventory" → "Stock Balance"
3. Search for "FG-DASH-001"
4. Verify stock increased by 180 PCS at WH-B1
5. Click on item to view stock ledger
6. Verify transaction: Production completion posted
7. Logout

---

## Phase 10: Customer Order & Delivery

### 10.1 Create Customer Invoice
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/ar/invoices/new`

1. Login with: acctg.officer@ogamierp.local / AcctgManager@1234!
2. Click sidebar: "AR" → "Invoices"
3. Click "New Invoice"
4. Fill form:
   - Invoice Number: (auto-generated)
   - Customer: CUST-001 (Toyota Motor Philippines)
   - Invoice Date: 2026-03-28
   - Due Date: 2026-05-12 (Net 45)
   - Delivery Schedule: DS-2026-03-001
5. Add line items:
   - Item: FG-DASH-001 (Dashboard Panel Assembly)
   - Quantity: 180 PCS
   - Unit Price: 1,500.00
   - Total: 270,000.00
6. Add VAT (12%):
   - Subtotal: ₱270,000.00
   - VAT: ₱32,400.00
   - Total: ₱302,400.00
7. Click "Create Invoice"
8. Verify invoice status: "Draft"

### 10.2 Approve Customer Invoice (VP)
**User:** vp@ogamierp.local / VicePresident@1!
**Page:** `/ar/invoices`

1. Logout from accounting officer
2. Login with: vp@ogamierp.local / VicePresident@1!
3. Click sidebar: "AR" → "Invoices"
4. Find invoice from step 10.1
5. Click on invoice number
6. Click "Approve"
7. Add remarks: "Approved for billing"
8. Click "Confirm Approval"
9. Verify status changes to "Approved"
10. Logout

### 10.3 Post Customer Invoice to GL
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/ar/invoices`

1. Login with: acctg.officer@ogamierp.local / AcctgManager@1234!
2. Find approved invoice
3. Click on invoice number
4. Verify GL entries preview:
   - DR: Accounts Receivable (1100) ₱302,400.00
   - CR: Sales Revenue (4000) ₱270,000.00
   - CR: Output VAT (2100) ₱32,400.00
5. Click "Post to GL"
6. Verify status changes to "Posted"
7. Verify journal entry created
8. Logout



### 10.4 Create Delivery Receipt
**User:** warehouse.head@ogamierp.local / Head@123456789!
**Page:** `/delivery/receipts/new`

1. Login with: warehouse.head@ogamierp.local / Head@123456789!
2. Click sidebar: "Delivery" → "Delivery Receipts"
3. Click "New Delivery Receipt"
4. Fill form:
   - DR Number: (auto-generated)
   - Customer Invoice: Select invoice from step 10.3
   - Delivery Date: 2026-03-30
   - Delivered By: (current user)
   - Vehicle: (select available vehicle)
   - Driver: Juan Dela Cruz
5. Verify line items from invoice:
   - FG-DASH-001: 180 PCS
6. Click "Create Delivery Receipt"
7. Verify DR created
8. Click "Mark as Delivered"
9. Verify stock deducted from WH-B1
10. Check stock ledger:
    - FG-DASH-001: 180 - 180 = 0 PCS remaining

---

## Phase 11: Financial Reports & Verification

### 11.1 View General Ledger
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/accounting/gl`

1. Login with: acctg.officer@ogamierp.local / AcctgManager@1234!
2. Click sidebar: "Accounting" → "General Ledger"
3. Filter by date range: 2026-03-01 to 2026-03-31
4. Verify all transactions posted:
   - Vendor invoice (AP)
   - Customer invoice (AR)
   - Inventory movements
5. Export to Excel for review

### 11.2 View Trial Balance
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/accounting/trial-balance`

1. Click sidebar: "Accounting" → "Trial Balance"
2. Select period: March 2026
3. Verify balances:
   - Cash: ₱5,000,000.00 (opening)
   - Accounts Receivable: ₱302,400.00 (DR)
   - Inventory: ₱133,000.00 (DR)
   - Accounts Payable: ₱148,960.00 (CR)
   - Sales Revenue: ₱270,000.00 (CR)
   - COGS: (calculated)
4. Verify debits = credits
5. Export to PDF

### 11.3 View Income Statement
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/accounting/income-statement`

1. Click sidebar: "Accounting" → "Income Statement"
2. Select period: March 2026
3. Verify:
   - Revenue: ₱270,000.00
   - Cost of Goods Sold: (calculated from inventory)
   - Gross Profit: (Revenue - COGS)
   - Operating Expenses: (if any)
   - Net Income: (Gross Profit - Expenses)
4. Export to PDF

### 11.4 View Balance Sheet
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/accounting/balance-sheet`

1. Click sidebar: "Accounting" → "Balance Sheet"
2. As of date: 2026-03-31
3. Verify:
   - Assets:
     * Cash: ₱5,000,000.00
     * Accounts Receivable: ₱302,400.00
     * Inventory: (remaining stock value)
   - Liabilities:
     * Accounts Payable: ₱148,960.00
   - Equity: (calculated)
4. Verify Assets = Liabilities + Equity
5. Export to PDF

### 11.5 View AP Aging Report
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/accounting/ap/aging-report`

1. Click sidebar: "Accounting" → "AP Aging Report"
2. As of date: 2026-03-31
3. Verify vendor balances:
   - VEND-001 (ABC Plastics): ₱148,960.00
   - Due date: 2026-04-17
   - Aging: Current (0-30 days)
4. Export to Excel

### 11.6 View AR Aging Report
**User:** acctg.officer@ogamierp.local / AcctgManager@1234!
**Page:** `/ar/aging-report`

1. Click sidebar: "AR" → "AR Aging Report"
2. As of date: 2026-03-31
3. Verify customer balances:
   - CUST-001 (Toyota): ₱302,400.00
   - Due date: 2026-05-12
   - Aging: Current (0-30 days)
4. Export to Excel

---

## Test Completion Summary

### Modules Tested
✅ Banking Setup
✅ Vendor Management
✅ Customer Management
✅ Inventory Management
✅ Procurement (PR → PO → GR)
✅ Accounts Payable
✅ Production Planning (BOM, Delivery Schedule)
✅ Production Orders
✅ Material Requisition
✅ Production Execution
✅ Quality Control (Inspection, NCR)
✅ Finished Goods Receipt
✅ Customer Invoicing
✅ Accounts Receivable
✅ Delivery Management
✅ Financial Reports (GL, Trial Balance, Income Statement, Balance Sheet)
✅ Aging Reports (AP/AR)

### Users Involved (SoD Enforced)
1. **Accounting Officer** - Financial setup, AP/AR, GL posting
2. **Purchasing Officer** - PR creation, PO management
3. **Warehouse Head** - Inventory, goods receipt, material issuance
4. **PPC Head** - BOM, delivery schedules, production planning
5. **Production Manager** - Production order approval, execution
6. **QC Manager** - Quality inspections, NCR management
7. **Vice President** - Final approvals (invoices, critical transactions)

### Key SoD Validations
- Purchasing Officer creates PR → Accounting Officer approves
- PPC Head creates production order → Production Manager approves
- Warehouse Head issues materials → Production Manager approves MR
- Accounting Officer creates invoices → VP approves
- Different users for creation vs. approval throughout

---

## Notes for Testers

1. **Always logout between user switches** to ensure proper session management
2. **Verify permissions** - each user should only see menu items they have access to
3. **Check SoD enforcement** - users should not be able to approve their own transactions
4. **Monitor stock levels** after each transaction
5. **Verify GL postings** after financial transactions
6. **Export reports** to verify data integrity
7. **Test error handling** by attempting unauthorized actions

---

## End of Test Guide

