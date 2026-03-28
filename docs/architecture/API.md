# Ogami ERP API Documentation

## Overview

Ogami ERP provides a comprehensive REST API organized by domain. All endpoints are versioned under `/api/v1/`.

**Base URL:** `http://localhost:8000/api/v1`

**Interactive Documentation:** Available at `/api/documentation` when running (via L5-Swagger)

---

## Authentication

All API endpoints (except login) require authentication via Laravel Sanctum session cookies.

### Login

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@ogamierp.local",
  "password": "Admin@1234567890!"
}
```

**Response:**
```json
{
  "token": "1|laravel_sanctum_token...",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@ogamierp.local",
    "roles": ["admin"],
    "permissions": ["*"]
  }
}
```

The session cookie is automatically set. Subsequent requests include the cookie.

### Logout

```http
POST /api/v1/auth/logout
```

---

## Response Format

### Success Response (List)

```json
{
  "data": [
    { ...item... },
    { ...item... }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 25,
    "total": 250
  }
}
```

### Success Response (Single)

```json
{
  "data": { ...item... }
}
```

### Error Response

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Validation failed.",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

**Common Error Codes:**
- `VALIDATION_ERROR` - Input validation failed
- `UNAUTHORIZED` - Authentication required
- `FORBIDDEN` - Permission denied
- `NOT_FOUND` - Resource not found
- `SOD_VIOLATION` - Same user cannot approve their own record
- `DOMAIN_ERROR` - Business logic violation

---

## Domains

### 1. HR (Employees, Departments, Positions)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/hr/employees` | GET | List employees |
| `/hr/employees` | POST | Create employee |
| `/hr/employees/{ulid}` | GET | Get employee details |
| `/hr/employees/{ulid}` | PUT | Update employee |
| `/hr/employees/{ulid}/transition` | POST | Change employment status |
| `/hr/departments` | GET | List departments |
| `/hr/positions` | GET | List positions |

**Employee Status Transitions:**
- `draft` → `active`
- `active` → `on_leave`, `suspended`
- `on_leave` → `active`, `resigned`, `terminated`
- `suspended` → `active`, `resigned`, `terminated`

### 2. Leave Management

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/leave/requests` | GET | List leave requests |
| `/leave/requests` | POST | Submit leave request |
| `/leave/requests/{ulid}` | GET | Get leave request |
| `/leave/requests/{ulid}/head-approve` | POST | Head approval (SoD enforced) |
| `/leave/requests/{ulid}/manager-check` | POST | Manager check (SoD enforced) |
| `/leave/requests/{ulid}/ga-process` | POST | GA processing (SoD enforced) |
| `/leave/balances` | GET | Get leave balances |

### 3. Attendance

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/attendance/logs` | GET | List attendance logs |
| `/attendance/overtime` | GET | List overtime requests |
| `/attendance/overtime` | POST | Submit overtime request |
| `/attendance/shifts` | GET | List shift schedules |

### 4. Payroll

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/payroll/runs` | GET | List payroll runs |
| `/payroll/runs` | POST | Create payroll run |
| `/payroll/runs/{ulid}` | GET | Get payroll run details |
| `/payroll/runs/{ulid}/compute` | POST | Compute payroll |
| `/payroll/runs/{ulid}/hr-approve` | POST | HR approval (SoD enforced) |
| `/payroll/runs/{ulid}/acctg-approve` | POST | Accounting approval (SoD enforced) |
| `/payroll/runs/{ulid}/vp-approve` | POST | VP approval (SoD enforced) |

**Payroll Run States:**
```
draft → scope_set → pre_run_checked → processing → computed → review → 
submitted → hr_approved → acctg_approved → vp_approved → locked → posted
```

### 5. Loans

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/loans` | GET | List loans |
| `/loans` | POST | Apply for loan |
| `/loans/{ulid}/head-note` | POST | Head notation (SoD enforced) |
| `/loans/{ulid}/officer-review` | POST | Officer review (SoD enforced) |
| `/loans/{ulid}/vp-approve` | POST | VP approval (SoD enforced) |

### 6. AP (Accounts Payable)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/accounting/vendors` | GET | List vendors |
| `/accounting/vendors` | POST | Create vendor |
| `/accounting/vendors/{id}` | PUT | Update vendor |
| `/accounting/ap/invoices` | GET | List AP invoices |
| `/accounting/ap/invoices` | POST | Create invoice |
| `/accounting/ap/invoices/{ulid}/head-note` | POST | Head approval (SoD enforced) |
| `/accounting/ap/invoices/{ulid}/approve` | POST | Final approval (SoD enforced) |
| `/accounting/ap/payments` | GET | List payments |
| `/accounting/ap/payments` | POST | Record payment |

**Vendor Accreditation Status:**
- `pending` - Pending review
- `accredited` - Approved for POs
- `suspended` - Temporarily blocked
- `blacklisted` - Permanently blocked

### 7. AR (Accounts Receivable)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/ar/customers` | GET | List customers |
| `/ar/customers` | POST | Create customer |
| `/ar/invoices` | GET | List customer invoices |
| `/ar/invoices` | POST | Create invoice |
| `/ar/payments` | GET | List payments |
| `/ar/payments` | POST | Record payment |

### 8. Accounting (GL)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/accounting/journal-entries` | GET | List journal entries |
| `/accounting/journal-entries` | POST | Create JE |
| `/accounting/journal-entries/{ulid}/post` | POST | Post JE to GL |
| `/accounting/accounts` | GET | Chart of accounts |
| `/accounting/trial-balance` | GET | Trial balance report |
| `/accounting/balance-sheet` | GET | Balance sheet |
| `/accounting/income-statement` | GET | Income statement |

**JE Status Flow:**
```
draft → submitted → posted
   ↓
stale → cancelled
```

### 9. Tax

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/tax/vat-ledger` | GET | VAT ledger entries |
| `/tax/bir-filings` | GET | BIR filings |
| `/tax/bir-filings` | POST | Create BIR filing |

### 10. Budget

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/budget/cost-centers` | GET | List cost centers |
| `/budget/annual-budgets` | GET | List annual budgets |
| `/budget/utilization` | GET | Budget utilization report |

### 11. Fixed Assets

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/fixed-assets/assets` | GET | List fixed assets |
| `/fixed-assets/assets` | POST | Create asset |
| `/fixed-assets/assets/{ulid}/depreciate` | POST | Run depreciation |
| `/fixed-assets/disposals` | POST | Dispose asset |

### 12. Inventory

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/inventory/items` | GET | List item masters |
| `/inventory/items` | POST | Create item |
| `/inventory/items/{ulid}` | GET | Get item details |
| `/inventory/categories` | GET | List categories |
| `/inventory/material-requisitions` | GET | List MRs |
| `/inventory/material-requisitions` | POST | Create MR |
| `/inventory/stock-ledger` | GET | Stock transactions |

**Item Types:**
- `raw_material` - Raw materials
- `semi_finished` - Work in progress
- `finished_good` - Finished products
- `consumable` - Office supplies, etc.
- `spare_part` - Machine parts

### 13. Procurement

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/procurement/purchase-requests` | GET | List PRs |
| `/procurement/purchase-requests` | POST | Create PR |
| `/procurement/purchase-requests/{ulid}/submit` | POST | Submit PR |
| `/procurement/purchase-requests/{ulid}/note` | POST | Head note (SoD enforced) |
| `/procurement/purchase-requests/{ulid}/check` | POST | Manager check (SoD enforced) |
| `/procurement/purchase-requests/{ulid}/review` | POST | Officer review (SoD enforced) |
| `/procurement/purchase-requests/{ulid}/vp-approve` | POST | VP approval (SoD enforced) |
| `/procurement/purchase-orders` | GET | List POs |
| `/procurement/purchase-orders/{ulid}/send` | POST | Send PO to vendor |
| `/procurement/goods-receipts` | GET | List GRs |
| `/procurement/goods-receipts` | POST | Create GR |

**PR Approval Chain (SoD enforced):**
```
Staff creates → Head notes → Manager checks → Officer reviews → VP approves
```

### 14. Production

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/production/orders` | GET | List production orders |
| `/production/orders` | POST | Create production order |
| `/production/orders/{ulid}/release` | POST | Release order |
| `/production/orders/{ulid}/record-output` | POST | Record output |
| `/production/orders/{ulid}/complete` | POST | Complete order |
| `/production/boms` | GET | List BOMs |
| `/production/delivery-schedules` | GET | List delivery schedules |

### 15. QC (Quality Control)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/qc/inspections` | GET | List inspections |
| `/qc/inspections` | POST | Create inspection |
| `/qc/inspections/{ulid}/record-results` | POST | Record results |
| `/qc/templates` | GET | List inspection templates |
| `/qc/ncrs` | GET | List NCRs |
| `/qc/ncrs` | POST | Create NCR |
| `/qc/capa` | GET | List CAPA actions |
| `/qc/capa` | POST | Create CAPA |

**Inspection Stages:**
- `IQC` - Incoming Quality Control
- `IPQC` - In-Process Quality Control
- `OQC` - Outgoing Quality Control

### 16. Maintenance

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/maintenance/equipment` | GET | List equipment |
| `/maintenance/work-orders` | GET | List work orders |
| `/maintenance/work-orders` | POST | Create work order |
| `/maintenance/pm-schedules` | GET | List PM schedules |

### 17. Mold

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/mold/masters` | GET | List molds |
| `/mold/masters/{id}/record-shots` | POST | Record shot count |

### 18. Delivery

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/delivery/shipments` | GET | List shipments |
| `/delivery/receipts` | GET | List delivery receipts |
| `/delivery/vehicles` | GET | List vehicles |

### 19. ISO

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/iso/documents` | GET | List controlled documents |
| `/iso/audits` | GET | List internal audits |
| `/iso/findings` | GET | List audit findings |
| `/iso/capa` | GET | List CAPA actions |

### 20. CRM

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/crm/tickets` | GET | List support tickets |
| `/crm/tickets` | POST | Create ticket |
| `/crm/tickets/{ulid}/reply` | POST | Add reply |

---

## Common Patterns

### Filtering

```http
GET /api/v1/hr/employees?department_id=1&employment_status=active
GET /api/v1/accounting/ap/invoices?vendor_id=5&status=pending_approval
GET /api/v1/procurement/purchase-requests?status=approved&urgency=urgent
```

### Pagination

```http
GET /api/v1/hr/employees?page=2&per_page=50
```

### Date Ranges

```http
GET /api/v1/accounting/ap/invoices?due_date_from=2024-01-01&due_date_to=2024-01-31
GET /api/v1/attendance/logs?log_date_from=2024-01-01&log_date_to=2024-01-31
```

---

## Validation Rules

### Employee Government IDs

- **TIN**: `XXX-XXX-XXX-XXX` (12 digits with dashes)
- **SSS**: `XX-XXXXXXX-X` (10 digits)
- **PhilHealth**: `XX-XXXXXXXXX-X` (12 digits)
- **Pag-IBIG**: `XXXX-XXXX-XXXX` (12 digits)

### Currency

All monetary values are stored as integers in **centavos**:
- ₱25,000.00 = `2500000` centavos
- When sending/receiving: use integer values
- For display: divide by 100

### Dates

- Format: `YYYY-MM-DD` for dates
- Format: `YYYY-MM-DD HH:MM:SS` for timestamps
- All dates in **Asia/Manila** timezone

---

## Rate Limiting

- **Read operations**: 120 requests/minute
- **Write operations**: 60 requests/minute
- **Auth endpoints**: 10 requests/minute

Headers returned:
```http
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 115
X-RateLimit-Reset: 1699999999
```

---

## SoD (Segregation of Duties)

Certain endpoints enforce SoD - the user who created a record cannot approve it:

- Leave approvals
- Loan approvals
- PR/PO approvals
- AP invoice approvals
- Payroll approvals
- Journal entry approvals

**Exception:** `admin` and `super_admin` roles can bypass SoD.

---

## WebSocket Events

Real-time updates via Laravel Reverb:

```javascript
// Subscribe to notifications
Echo.private(`App.Models.User.${userId}`)
    .notification((notification) => {
        console.log(notification);
    });

// Subscribe to department updates
Echo.private(`department.${departmentId}`)
    .listen('EmployeeActivated', (e) => {
        console.log(e.employee);
    });
```

---

## SDKs & Tools

### Postman Collection
Import the OpenAPI spec at `/api/documentation` into Postman.

### Frontend Integration

```typescript
// Using the API client
import api from '@/lib/api';

// List employees
const { data } = await api.get('/hr/employees', {
  params: { department_id: 1 }
});

// Create PR
const response = await api.post('/procurement/purchase-requests', {
  department_id: 1,
  justification: 'Need office supplies',
  items: [{
    item_description: 'A4 Paper',
    unit_of_measure: 'ream',
    quantity: 10
  }]
});
```

---

## Changelog

### v1.0.0 (2024-03)
- Initial API release
- All 20 domains implemented
- Authentication via Sanctum
- SoD enforcement on approval workflows
- Government ID format validation
- Standardized response format
