# ERP Automation Features - Testing Guide

## Prerequisites

Before testing, ensure you have:
1. Super admin or admin account
2. At least one employee
3. Chart of Accounts seeded
4. At least one vendor
5. At least one item master (finished goods)
6. At least one customer

Run this to create test data:
```bash
cd /home/kwat0g/Desktop/ogamiPHP
php artisan db:seed --class=ChartOfAccountsSeeder
php artisan db:seed --class=SampleDataSeeder
```

---

## 1. CLIENT PORTAL ORDER SYSTEM

### 1.1 Create a Customer Portal Account

**Backend Steps:**
```bash
# Create customer with portal access
php artisan tinker --execute="
use App\Models\User;
use App\Domains\AR\Models\Customer;

\$customer = Customer::create([
    'name' => 'Test Customer Corp',
    'email' => 'test@customer.com',
    'phone' => '09123456789',
    'address' => '123 Test Street, Manila',
    'is_active' => true,
]);

\$user = User::create([
    'name' => 'Test Customer User',
    'email' => 'test@customer.com',
    'password' => bcrypt('password123'),
    'customer_id' => \$customer->id,
    'is_active' => true,
]);
\$user->assignRole('client');

echo 'Customer created: ' . \$customer->id . '\n';
echo 'User created: ' . \$user->email . '\n';
"
```

### 1.2 Test Client Portal Flow

**Step 1: Login as Customer**
1. Go to `/login`
2. Login with: `test@customer.com` / `password123`
3. Should redirect to client portal

**Step 2: Browse Products**
1. Click "Shop Products" in sidebar
2. Should see list of finished goods
3. Click "Add to Cart" on any product

**Step 3: Submit Order**
1. Click cart icon (top right)
2. Adjust quantity if needed
3. Select "Requested Delivery Date"
4. Add order notes (optional)
5. Click "Submit Order"
6. Should see success toast

**Expected:** Order created with status "pending"

### 1.3 Test Sales Review Workflow

**Step 1: Login as Sales Staff**
1. Open incognito window
2. Login as admin or sales user
3. Ensure user has `sales.order_review` permission

**Step 2: Review Orders**
1. Go to `/sales/client-orders`
2. Should see the pending order
3. Click "View Details" (eye icon)

**Step 3: Approve Order**
1. Click "Approve" button
2. Add internal notes (optional)
3. Confirm
4. Should see success message
5. Check that Delivery Schedule was created

**Expected:** Order status changes to "approved"

### 1.4 Test Negotiation Flow

**Step 1: Submit Another Order**
1. Back in client portal
2. Add items to cart
3. Submit order

**Step 2: Sales Negotiates**
1. As sales user, view the order
2. Click "Negotiate" button
3. Select reason: "Insufficient stock"
4. Propose new delivery date
5. Add notes
6. Send proposal

**Step 3: Client Responds**
1. In client portal, view order
2. Should see "Negotiating" status
3. Click "Respond to Proposal"
4. Options:
   - **Accept**: Order goes back to pending for approval
   - **Counter**: Propose different date
   - **Cancel**: Withdraw order

---

## 2. JOURNAL ENTRY TEMPLATES

### 2.1 View Templates

**Step 1: Navigate to Journal Entry Form**
1. Go to `/accounting/journal-entries`
2. Click "New Journal Entry"

**Step 2: See Templates**
1. Look for blue "Use Template" box
2. Dropdown should show:
   - Payroll Accrual (System)
   - Loan Proceeds (System)
   - Asset Purchase on Credit (System)

### 2.2 Apply Template

**Step 1: Select Template**
1. Select "Payroll Accrual" from dropdown
2. Click "Apply"

**Step 2: Verify Lines**
1. Should auto-populate lines:
   - Debit: Salaries and Wages Expense
   - Credit: SSS Contributions Payable
   - Credit: PhilHealth Payable
   - Credit: PagIBIG Payable
   - Credit: Payroll Payable

**Step 3: Fill Amounts**
1. Enter amount in debit line (e.g., 50000)
2. Enter amounts in credit lines
3. Ensure debits = credits
4. Click "Save as Draft"

### 2.3 Create Custom Template

**Step 1: Create Lines**
1. Manually add journal entry lines
2. Select accounts
3. Enter amounts

**Step 2: Save as Template**
1. Click "Save as Template" button
2. Enter template name: "Monthly Utilities"
3. Enter description: "Electricity, water, internet"
4. Click "Save Template"

**Step 3: Verify**
1. Clear form or refresh
2. Select template dropdown
3. Should see "Monthly Utilities" (user template)
4. Apply it - lines should populate

---

## 3. PURCHASE REQUEST DUPLICATION

### 3.1 Create Original PR

**Step 1: Create PR**
1. Go to `/procurement/purchase-requests`
2. Click "New Purchase Request"
3. Fill in:
   - Department
   - Justification: "Office supplies"
4. Add line items:
   - Bond paper, 10 reams, ₱250/ream
   - Ink cartridge, 2 pcs, ₱800/pc
5. Submit for approval

**Step 2: Approve PR**
1. As manager, approve the PR
2. As VP, approve the PR
3. PR status should be "approved"

### 3.2 Duplicate PR

**Step 1: Find Approved PR**
1. Go to PR list
2. Find the approved PR

**Step 2: Click Duplicate**
1. Click "Duplicate" button
2. Should see "Copying..." spinner
3. Should navigate to new PR edit page

**Step 3: Verify Duplicated Data**
1. Check that line items are copied
2. Status should be "draft"
3. Reference number should be new

**Step 4: Modify and Submit**
1. Change quantities
2. Add/remove items
3. Submit as normal

---

## 4. VENDOR INVOICE FROM PO

### 4.1 Create PO First

**Step 1: Create PR**
1. Create Purchase Request
2. Add items
3. Submit and get approvals

**Step 2: Convert PR to PO**
1. Purchasing creates PO from approved PR
2. Assign vendor
3. Send PO to vendor
4. PO status: "sent"

### 4.2 Create Invoice from PO

**Step 1: Go to AP Invoices**
1. Navigate to `/accounting/ap/invoices`

**Step 2: Create from PO**
1. Click "From PO" button
2. Modal shows eligible POs (status: sent/partial)
3. Select the PO

**Step 3: Verify Auto-Populated Data**
1. Vendor should be pre-selected
2. Line items should match PO
3. Quantities and prices should match

**Step 4: Adjust for Partial Receipt**
1. If partial delivery, reduce quantities
2. Example: Ordered 100, received 50
3. Change quantity to 50
4. Save invoice

**Expected:** Invoice created with status "draft"

---

## 5. PRODUCTION ORDER SMART DEFAULTS

### 5.1 Setup BOM

**Step 1: Create BOM**
1. Go to `/production/boms`
2. Click "New BOM"
3. Select product item
4. Add components
5. Set "Standard Production Days" (e.g., 5)
6. Save

### 5.2 Test Smart Defaults

**Step 1: Create Production Order**
1. Go to `/production/orders`
2. Click "New Production Order"

**Step 2: Select Product**
1. Select the product with BOM
2. Watch for:
   - BOM auto-selected (if only one exists)
   - Green indicator showing auto-selection

**Step 3: Enter Start Date**
1. Set "Target Start Date" to today
2. System calculates "Target End Date" = Start + 5 days
3. Green indicator shows auto-calculation

**Step 4: Override (Optional)**
1. Change BOM manually
2. Change end date manually
3. Form should accept overrides

**Step 5: Save**
1. Click "Create Production Order"
2. Should save with selected values

---

## 6. ATTENDANCE EXCEL TEMPLATE

### 6.1 Download Template

**Step 1: Go to Attendance Import**
1. Navigate to `/hr/attendance/import`

**Step 2: Download Template**
1. Click "Download Excel Template" button
2. File should download: `attendance_template_YYYY-MM-DD.xlsx`

### 6.2 Fill Template

**Step 1: Open Excel**
1. Open downloaded file
2. Should see columns:
   - employee_code (pre-filled)
   - employee_name (pre-filled)
   - work_date (empty)
   - time_in (empty)
   - time_out (empty)
   - notes (empty)

**Step 2: Fill Data**
```
employee_code | employee_name | work_date  | time_in | time_out | notes
EMP-2025-001  | John Doe      | 2026-03-20 | 08:00   | 17:00    | 
EMP-2025-002  | Jane Smith    | 2026-03-20 | 08:30   | 17:30    | Late
```

### 6.3 Upload Attendance

**Step 1: Save and Upload**
1. Save Excel as CSV
2. Go to attendance import page
3. Select file
4. Click "Upload"

**Step 2: Verify Import**
1. Check validation report
2. Should show success count
3. Go to attendance list
4. Should see imported records

---

## API Testing (For Developers)

### Test Client Order API

```bash
# 1. Submit Order (as customer)
curl -X POST http://localhost:8000/api/v1/crm/client-orders \
  -H "Authorization: Bearer {customer_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {"item_master_id": 1, "quantity": 10, "unit_price_centavos": 50000}
    ],
    "requested_delivery_date": "2026-04-01",
    "notes": "Urgent order"
  }'

# 2. List Orders (as sales)
curl http://localhost:8000/api/v1/crm/client-orders \
  -H "Authorization: Bearer {sales_token}"

# 3. Approve Order
curl -X POST http://localhost:8000/api/v1/crm/client-orders/1/approve \
  -H "Authorization: Bearer {sales_token}" \
  -H "Content-Type: application/json" \
  -d '{"notes": "Approved, in stock"}'
```

### Test Journal Entry Templates API

```bash
# 1. List Templates
curl http://localhost:8000/api/v1/accounting/journal-entry-templates \
  -H "Authorization: Bearer {token}"

# 2. Apply Template
curl http://localhost:8000/api/v1/accounting/journal-entry-templates/1/apply \
  -H "Authorization: Bearer {token}"

# 3. Create Custom Template
curl -X POST http://localhost:8000/api/v1/accounting/journal-entry-templates \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Custom Template",
    "description": "Test",
    "lines": [
      {"account_id": 1, "debit_or_credit": "debit"},
      {"account_id": 2, "debit_or_credit": "credit"}
    ]
  }'
```

### Test PR Duplication API

```bash
# Duplicate PR
curl -X POST http://localhost:8000/api/v1/procurement/purchase-requests/ABC123/duplicate \
  -H "Authorization: Bearer {token}"
```

### Test Invoice from PO API

```bash
# Create Invoice from PO
curl -X POST http://localhost:8000/api/v1/accounting/ap/invoices/from-po \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"purchase_order_id": 1}'
```

### Test Smart Defaults API

```bash
# Get Smart Defaults
curl "http://localhost:8000/api/v1/production/orders/smart-defaults?product_item_id=1&target_start_date=2026-03-20" \
  -H "Authorization: Bearer {token}"
```

### Test Attendance Template API

```bash
# Download Template
curl http://localhost:8000/api/v1/attendance/template \
  -H "Authorization: Bearer {token}" \
  -o attendance_template.xlsx
```

---

## Troubleshooting

### Common Issues

**1. "No permissions" error**
- Run: `php artisan db:seed --class=RolePermissionSeeder`
- Or assign permissions manually

**2. Chart of Accounts not found**
- Run: `php artisan db:seed --class=ChartOfAccountsSeeder`

**3. Client can't see products**
- Ensure items have `item_type = 'finished_goods'`
- Ensure items are active

**4. Template lines empty**
- Check that accounts in template still exist
- Check that accounts are active

**5. PO not showing in "Create from PO"**
- PO must have status "sent" or "partially_received"
- PO must not already have an invoice

---

## Success Criteria

| Feature | Test Passed If |
|---------|----------------|
| Client Portal | Customer can submit order, sales can approve |
| JE Templates | Template applies lines, user fills amounts only |
| PR Duplicate | New PR created with copied items, status = draft |
| Invoice from PO | Invoice auto-populates with PO items |
| Smart Defaults | BOM and dates auto-suggest on product select |
| Attendance Template | Excel downloads with employee codes pre-filled |

---

## Demo Script for Panelists

### 5-Minute Demo Flow

**Minute 1: Client Portal**
1. Login as customer → Browse products → Add to cart → Submit order
2. Switch to sales → Show pending order → Approve it

**Minute 2: Journal Entry Templates**
1. New Journal Entry → Select "Payroll Accrual" template
2. Show auto-populated lines → Enter amounts → Save

**Minute 3: PR Duplication**
1. Show existing approved PR
2. Click "Duplicate" → Show new PR with copied items

**Minute 4: Invoice from PO**
1. Show PO list → Click "Create from PO"
2. Select PO → Show auto-populated invoice

**Minute 5: Smart Defaults**
1. New Production Order → Select product
2. Watch BOM auto-select and dates auto-calculate

**Closing:** "These features reduce data entry time by 60-80%, transforming the ERP from a digital paper system into an intelligent productivity tool."
