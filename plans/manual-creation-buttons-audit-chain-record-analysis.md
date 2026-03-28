# Manual Creation Buttons Audit - Chain Record Analysis (v2)

## Overview

This audit identifies every "New" / "Create" / "Add" button in the ERP and categorizes them based on whether manual creation is appropriate or if the record should only come through a workflow chain or self-service.

---

## REMOVE - Should Not Have Manual Creation

### 1. Sales Orders - Remove "New Sales Order" button
- **Page**: `SalesOrderListPage` 
- **Route**: `/sales/orders/new`
- **Why**: Sales orders should only come from quotation conversion. The `QuotationDetailPage` already has a "Convert to Order" button. Manual creation bypasses the quotation approval workflow.

### 2. Production Orders - Remove "New Order" button
- **Page**: `ProductionOrderListPage`
- **Route**: `/production/orders/new`
- **Why**: Production orders should come from delivery schedules. The `DeliveryScheduleDetailPage` already has a "Create Work Order" action. Manual creation bypasses the schedule-to-production chain.

### 3. Loans (HR Admin) - Remove "New Loan" button
- **Page**: `LoanListPage` (HR admin at `/hr/loans`)
- **Route**: `/hr/loans/new`
- **Why**: Loan applications should only come from the employee themselves via self-service (`/me/loans`). HR's role is to review and approve loan applications, not create them on behalf of employees. The employee should be the one applying.

---

## MODIFY - Change Label or Add Chain Path

### 4. Leave (HR Admin) - Rename to "File on Behalf"
- **Page**: `LeaveListPage` (HR admin at `/hr/leave`)
- **Current**: "+ File Leave" button
- **Change**: Rename to "File on Behalf" and ensure the form requires selecting an employee (which it already does since it uses `leaves.file_on_behalf` permission). This is a legitimate HR function -- recording sick leave when an employee calls in, filing leave for employees who can't access the system.

### 5. Customer Invoices - Add "From Sales Order" path
- **Page**: `CustomerInvoicesPage`
- **Current**: Only "New Invoice" (manual)
- **Change**: Add a "From Sales Order" button similar to APInvoicesPage "From PO" pattern. Manual creation stays for ad-hoc invoices (consulting fees, adjustments).

### 6. AP Invoices - Make "From PO" more prominent
- **Page**: `APInvoicesPage`
- **Current**: "From PO" and "New Invoice" buttons side by side
- **Change**: Make "From PO" the primary button (solid dark), "New Invoice" the secondary (outline/ghost). The chain path should be the default.

---

## KEEP - Correctly Manual

### Master Data (always manual)
- Employees, Vendors, Customers, Items, Equipment, Molds, Bank Accounts, Chart of Accounts, Fiscal Periods, Item Categories, Warehouse Locations, Cost Centers, Fixed Assets, Pay Periods, Departments, Positions, Shifts

### Workflow Starters (correct starting points)
- Purchase Requests, Payroll Runs, Delivery Schedules, BOMs, Material Requisitions, QC Inspections, NCRs, Maintenance Work Orders, Vendor RFQs, CRM Tickets, Quotations

### Self-Service (correct requester filing)
- Leave via MyLeavesPage (employee files own)
- OT via MyOTPage (employee files own)
- Loans via MyLoansPage (employee applies -- though no create button found here, need to verify)

### Accounting (manual is standard practice)
- Journal Entries -- manual JE creation is essential for adjusting entries, corrections, accruals, reclassifications
- Bank Reconciliations -- initiated manually by accountants

### Correctly Read-Only HR Admin Pages
- OT Admin (`/hr/overtime`) -- already has NO create button, comment says "read-only"

---

## Implementation Plan

### Phase 1: Remove chain-only creation buttons
- [ ] Remove "New Sales Order" button from `SalesOrderListPage` and remove `/sales/orders/new` route
- [ ] Remove "New Order" button from `ProductionOrderListPage` and remove `/production/orders/new` route
- [ ] Remove "New Loan" button from HR admin `LoanListPage` at `/hr/loans` and remove `/hr/loans/new` route

### Phase 2: Modify labels and add chain paths
- [ ] Rename "+ File Leave" to "File on Behalf" on HR admin `LeaveListPage`
- [ ] Add "From Sales Order" button to `CustomerInvoicesPage`
- [ ] Make "From PO" the primary button on `APInvoicesPage`

### Phase 3: Verify self-service
- [ ] Verify `MyLoansPage` has a loan application button (employee self-service)
- [ ] Verify the OT admin page stays read-only
