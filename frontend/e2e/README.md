# Ogami ERP E2E Testing Documentation

> **Note:** The `ManufacturingEmployeeSeeder` properly hashes passwords. Run it once and all test accounts work immediately!

## 📚 Documentation Files

| File | Purpose | When to Use |
|------|---------|-------------|
| [**QUICK_START.md**](QUICK_START.md) | Fastest way to start testing | **Start here!** (2 min read) |
| [**MANUAL_RBAC_TEST_GUIDE.md**](MANUAL_RBAC_TEST_GUIDE.md) | Complete manual testing by role | Manual testing (15 min) |
| [**WORKFLOW_QUICK_REFERENCE.md**](WORKFLOW_QUICK_REFERENCE.md) | Workflow diagrams & approval steps | Testing workflows (10 min) |
| [**RBAC_STEP_BY_STEP_TEST_GUIDE.md**](RBAC_STEP_BY_STEP_TEST_GUIDE.md) | Automated test commands | Running E2E tests (20 min) |

---

## 🚀 Quick Start (Choose Your Path)

### Option A: Manual Testing (Recommended - Fastest)

```bash
# 1. Start servers
php artisan serve          # Terminal 1
pnpm dev                   # Terminal 2

# 2. Seed test accounts (one-time)
cd /home/kwat0g/Desktop/ogamiPHP
php artisan db:seed --class=ManufacturingEmployeeSeeder

# 3. Open browser and test
open http://localhost:5173/login
```

Then follow: [**MANUAL_RBAC_TEST_GUIDE.md**](MANUAL_RBAC_TEST_GUIDE.md)

---

### Option B: Interactive Automated Tests

```bash
cd /home/kwat0g/Desktop/ogamiPHP/frontend/e2e
./test-rbac-interactive.sh
```

Select test groups from the interactive menu.

---

### Option C: Full Automated Suite

```bash
cd /home/kwat0g/Desktop/ogamiPHP/frontend/e2e
./run-rbac-tests.sh
```

Runs all 40 automated tests (~10-15 minutes).

---

## 🎯 What to Test

### Critical RBAC Tests (Must Pass)

| # | Test | Login As | Action | Expected |
|---|------|----------|--------|----------|
| 1 | Production → Payroll blocked | prod.manager | Access /hr/payroll | 403 Forbidden |
| 2 | Production → Inventory blocked | prod.manager | Access /inventory/categories | 403 Forbidden |
| 3 | Warehouse → Inventory allowed | warehouse.head | Access /inventory/items | ✅ Can create items |
| 4 | HR → Production blocked | hr.manager | Access /production/orders | 403 Forbidden |
| 5 | Admin → Business blocked | admin | Access /hr/employees | 403 Forbidden |

### Critical Workflow Tests (Must Pass)

| Workflow | Test Steps | SoD Check |
|----------|------------|-----------|
| Purchase Request | Officer creates → Head approves → VP approves (if >100k) | Officer cannot approve own |
| Payroll | HR computes → VP approves → Accounting posts | HR cannot approve own |
| Leave | Staff submits → Manager approves → HR records | Manager cannot approve own |

---

## 👤 Test Account Credentials

### Production & Manufacturing
| Email | Password | Role |
|-------|----------|------|
| prod.manager@ogamierp.local | Manager@12345! | Production Manager |
| production.head@ogamierp.local | Head@123456789! | Production Head |
| qc.manager@ogamierp.local | Manager@12345! | QC Manager |
| warehouse.head@ogamierp.local | Head@123456789! | Warehouse Head |
| plant.manager@ogamierp.local | Manager@12345! | Plant Manager |
| vp@ogamierp.local | VicePresident@1! | Vice President |

### Support Departments
| Email | Password | Role |
|-------|----------|------|
| hr.manager@ogamierp.local | HrManager@12345! | HR Manager |
| acctg.manager@ogamierp.local | Manager@12345! | Accounting Manager |
| purchasing.officer@ogamierp.local | Officer@12345! | Purchasing Officer |

### System
| Email | Password | Role |
|-------|----------|------|
| admin@ogamierp.local | Admin@12345! | System Admin |

---

## 📋 Quick Reference: What Each Role Sees

### Production Manager
**✅ Can Access:** Production, QC/QA, Inventory (MRQ only), Maintenance, Mold, Delivery, Team Management  
**❌ Blocked From:** Payroll, Item Categories, Stock Ledger, Accounting, HR

### Warehouse Head
**✅ Can Access:** Full Inventory (items, categories, adjustments, stock ledger), Delivery, Team Management  
**❌ Blocked From:** Payroll, Production, Accounting

### HR Manager
**✅ Can Access:** Full HR, Team Management, Attendance, Payroll, Leave, Loans  
**❌ Blocked From:** Production, Inventory, Accounting

### Accounting Manager
**✅ Can Access:** Accounting, AP, AR, Banking, Fixed Assets, Budget, Reports, Tax  
**❌ Blocked From:** Production, Inventory (except costs), Payroll (accounting view only)

### VP
**✅ Can Access:** VP Approvals, Dashboard, Production, QC, Maintenance, Delivery, Inventory, Reports  
**✅ Can Approve:** Cross-department requests, Payroll, Journal Entries  
**❌ Blocked From:** Individual salary details, System admin

### Admin
**✅ Can Access:** System Administration ONLY  
**❌ Blocked From:** ALL business modules

---

## 🐛 Troubleshooting

### Account Locked
```bash
cd /home/kwat0g/Desktop/ogamiPHP
php artisan tinker --execute="
App\Models\User::where('email', 'prod.manager@ogamierp.local')
  ->update(['failed_login_attempts' => 0, 'locked_until' => null]);
"
```

### Rate Limited
```bash
php artisan cache:clear
```

### Reset All Test Accounts
```bash
php artisan db:seed --class=ManufacturingEmployeeSeeder
```

---

## 📂 File Structure

```
frontend/e2e/
├── README.md                          # This file - master index
├── QUICK_START.md                     # Quick start guide
├── MANUAL_RBAC_TEST_GUIDE.md          # Manual testing by role
├── WORKFLOW_QUICK_REFERENCE.md        # Workflow diagrams
├── RBAC_STEP_BY_STEP_TEST_GUIDE.md    # Automated test commands
│
├── rbac-comprehensive-ui.spec.ts      # 40 automated RBAC tests
├── workflows-comprehensive.spec.ts    # Workflow tests
├── api-integration.spec.ts            # API tests
├── module-detailed.spec.ts            # Module tests
│
├── test-rbac-interactive.sh           # Interactive test runner
├── run-rbac-tests.sh                  # Automated test runner
├── setup-test-accounts.sh             # Account setup (legacy)
│
└── helpers/
    ├── auth.ts                        # Auth utilities
    └── rate-limit.ts                  # Rate limit utilities
```

---

## ⏱️ Testing Time Estimates

| Method | Tests | Time | Use Case |
|--------|-------|------|----------|
| Manual - Critical only | 5 | 5 min | Daily development |
| Manual - Full | 40+ | 15 min | Weekly verification |
| Interactive | 40 | 10-15 min | Regression testing |
| Automated | 40 | 10-15 min | CI/CD pipeline |

---

## ✅ 5-Minute Quick Test Checklist

If short on time, verify these critical scenarios:

- [ ] **Production Manager Login** - Sidebar shows Production, no Payroll module
- [ ] **Production → Payroll URL** - http://localhost:5173/hr/payroll shows 403
- [ ] **Warehouse Head Login** - Can access and create Inventory items
- [ ] **Cross-Department Block** - HR cannot access Production
- [ ] **Admin Restricted** - Admin sees only System Admin, 403 on business modules
