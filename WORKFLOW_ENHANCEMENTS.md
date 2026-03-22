# Ogami ERP - Workflow Automation & Enhancement Opportunities

## ✅ ALREADY IMPLEMENTED (Production Ready)

### 1. Procurement Workflow
| Feature | Status | Details |
|---------|--------|---------|
| **PR → PO Auto-Creation** | ✅ Implemented | When PR is VP-approved, PO draft auto-created via `PurchaseOrderService::createFromApprovedPr()` |
| **3-Way Match** | ✅ Implemented | GR + PO + Invoice matching in `ThreeWayMatchService` |
| **Budget Enforcement** | ✅ Implemented | PR blocks if department budget exceeded |

### 2. Production Workflow
| Feature | Status | Details |
|---------|--------|---------|
| **Production Order → MRQ Auto-Creation** | ✅ Implemented | When PO is released, MRQ auto-created from BOM |
| **Stock Deduction on Release** | ✅ Implemented | BOM components auto-deducted from inventory |
| **QC Gate** | ✅ Implemented | Failed QC blocks production release (can override) |

### 3. Payroll → Accounting
| Feature | Status | Details |
|---------|--------|---------|
| **Payroll Auto-Post to GL** | ✅ Implemented | `PayrollAutoPostService` creates JE on payroll approval |
| **Idempotent Posting** | ✅ Implemented | Prevents duplicate JE for same payroll run |

### 4. Loan → Accounting
| Feature | Status | Details |
|---------|--------|---------|
| **Loan Disbursement → JE** | ✅ Implemented | Auto-creates JE for loan payable + cash |
| **Loan Payment → JE** | ✅ Implemented | Auto-creates JE when loan payment received |

### 5. AR/AP → Accounting
| Feature | Status | Details |
|---------|--------|---------|
| **Invoice Payment → JE** | ✅ Implemented | `ApPaymentPostingService` auto-posts payments |
| **Credit Notes → JE** | ✅ Implemented | Both vendor and customer credit notes auto-post |

### 6. Inventory Management
| Feature | Status | Details |
|---------|--------|---------|
| **Low Stock Alerts** | ✅ Implemented | Daily check at 07:00 AM, notifies purchasing |
| **Reorder Point Tracking** | ✅ Implemented | Per-item reorder points with notifications |

### 7. Fixed Assets
| Feature | Status | Details |
|---------|--------|---------|
| **Monthly Depreciation** | ✅ Implemented | Runs 1st of month at 03:00 AM, auto-posts JE |

### 8. Recurring Journals
| Feature | Status | Details |
|---------|--------|---------|
| **Auto-Generate from Templates** | ✅ Implemented | `journals:generate-recurring` runs daily |

### 9. Maintenance
| Feature | Status | Details |
|---------|--------|---------|
| **PM Work Order Auto-Creation** | ✅ Implemented | Daily at 06:00 AM from PM schedules |

### 10. Mold Management
| Feature | Status | Details |
|---------|--------|---------|
| **Shot Count Alerts** | ✅ Implemented | Daily check at 06:30 AM for molds near max shots |

### 11. AR Management
| Feature | Status | Details |
|---------|--------|---------|
| **Overdue Invoice Alerts** | ✅ Implemented | Daily at 08:30 AM, notifies AR officers |

---

## 🔧 ENHANCEMENT OPPORTUNITIES (Recommended for v2.0)

### HIGH PRIORITY - Procurement Automation

#### 1. **Auto-PR from Low Stock** (HIGH IMPACT)
**Current State:** Low stock sends notifications only  
**Enhancement:** Auto-create draft PR when stock ≤ reorder point
```
Trigger: Daily at 07:30 AM (after low stock check)
Action: Create draft PR for items below reorder point
Assignee: Purchasing Officer
Status: "draft" (requires review before submission)
```

**Files to Modify:**
- `app/Console/Commands/CheckReorderPointsCommand.php`
- `app/Domains/Procurement/Services/PurchaseRequestService.php` (add `autoCreateFromLowStock()`)

#### 2. **PO → Expected GR Auto-Creation** (MEDIUM IMPACT)
**Current State:** GR created manually when materials arrive  
**Enhancement:** When PO is approved, create "Expected GR" record
```
Trigger: PO status changes to "approved"
Action: Create ExpectedGR record with PO line items
Benefit: Warehouse knows what to expect, faster receiving
```

#### 3. **Vendor Performance Auto-Tracking** (MEDIUM IMPACT)
**Current State:** No vendor scoring visible  
**Enhancement:** Auto-calculate vendor metrics
```
Metrics:
- On-time delivery %
- Quality rejection %
- Price variance %
- Response time

Update: After each GR is completed
Display: Vendor list page with score badges
```

---

### HIGH PRIORITY - Production Automation

#### 4. **Delivery Schedule → Auto Production Order** (HIGH IMPACT)
**Current State:** Production Orders created manually from Delivery Schedules  
**Enhancement:** Auto-create PO when DS is confirmed
```
Trigger: Delivery Schedule status = "confirmed"
Action: 
  1. Check stock availability
  2. If insufficient stock → Create Production Order
  3. Link PO to Delivery Schedule
  4. Calculate start date based on lead time
```

**Files to Create:**
- `app/Jobs/Production/AutoCreateProductionOrderJob.php`
- Modify `DeliveryScheduleService::update()` to trigger

#### 5. **BOM Version Auto-Management** (MEDIUM IMPACT)
**Current State:** BOM changes are manual  
**Enhancement:** Track BOM revisions with effective dates
```
Features:
- Auto-archive old BOM when new version active
- Track which production orders used which BOM version
- Cost variance reporting between BOM versions
```

---

### MEDIUM PRIORITY - Inventory Automation

#### 6. **Cycle Count Auto-Scheduling** (MEDIUM IMPACT)
**Current State:** Physical counts scheduled manually  
**Enhancement:** Auto-schedule cycle counts based on ABC classification
```
Algorithm:
- A items (high value): Count monthly
- B items (medium): Count quarterly  
- C items (low): Count annually
- Trigger: Auto-create CycleCountSchedule
- Assign: To warehouse staff based on workload
```

#### 7. **Stock Reservation System** (HIGH IMPACT)
**Current State:** No reservation, first-come-first-served  
**Enhancement:** Reserve stock for production/orders
```
Reservation Types:
- Soft reservation (can be overridden)
- Hard reservation (production released)
- Customer reservation (committed orders)

Available Stock = On Hand - Reserved - Safety Stock
```

---

### MEDIUM PRIORITY - HR Automation

#### 8. **Employee Onboarding Checklist Auto-Generation** (MEDIUM IMPACT)
**Current State:** Static onboarding process  
**Enhancement:** Dynamic checklist based on role/department
```
Trigger: New employee created
Action: Generate checklist items:
  - IT: Create accounts, assign laptop
  - HR: Gov ID verification, benefits enrollment
  - Department: Workspace assignment, training schedule
  - Payroll: Bank account setup, tax forms

Track: % completion, due dates
Notify: When items overdue
```

#### 9. **Leave Balance Auto-Accrual** (HIGH IMPACT)
**Current State:** Leave balances updated manually or semi-annually  
**Enhancement:** Monthly auto-accrual based on tenure
```
Schedule: 1st of month at 02:00 AM
Logic:
  - SIL: 5 days after 1 year → auto-add on anniversary
  - Vacation: Monthly accrual based on years of service
  - Sick leave: Reset annually or carryover based on policy
```

#### 10. **Employee Clearance Workflow** (HIGH IMPACT)
**Current State:** No automated clearance process  
**Enhancement:** Multi-department clearance on resignation
```
Trigger: Employee status = "resigned"
Clearance Checklist:
  - IT: Return laptop, revoke access
  - Finance: Clear loans, final pay computation
  - HR: Return ID, exit interview
  - Department: Handover document, return tools
  - Warehouse: Clear material accountability

Block: Final pay release until all cleared
```

---

### LOW PRIORITY - Accounting Automation

#### 11. **Bank Reconciliation Auto-Matching** (MEDIUM IMPACT)
**Current State:** Manual matching of bank transactions  
**Enhancement:** Auto-match based on amount + reference
```
Matching Rules:
- Exact amount + invoice number in reference → Auto-match
- Amount within 1% + date within 3 days → Suggest match
- Unmatched items → Flag for review

Confidence Score: >95% auto-match, 80-95% suggest, <80% manual
```

#### 12. **Withholding Tax Auto-Generation** (MEDIUM IMPACT)
**Current State:** 2307 forms generated per payment  
**Enhancement:** Batch monthly generation
```
Schedule: Monthly on 5th
Action: Generate consolidated 2307 for all vendors
Auto-file: To BIR (if API available)
Notify: Accounting when ready for distribution
```

---

### NICE TO HAVE - Advanced Features

#### 13. **Demand Forecasting Integration** (LOW PRIORITY)
```
Based on:
- Historical sales/production data
- Seasonal patterns
- Delivery schedules

Output:
- Suggested production plan
- Material requirements forecast
- Cash flow impact projection
```

#### 14. **Smart Approval Routing** (LOW PRIORITY)
```
Current: Static approval hierarchy
Enhancement: Dynamic routing based on amount

Examples:
- PR < 50K: Manager approval only
- PR 50K-200K: Manager + Officer
- PR > 200K: Full hierarchy + VP
- PR > 1M: Board approval required
```

#### 15. **Inter-Company Transactions** (LOW PRIORITY)
```
Feature: Auto-create corresponding JE in related company
Use Case: Multi-entity manufacturing groups

Example:
- Company A sells to Company B
- Auto-create AR in A + AP in B
- Auto-reconcile on payment
```

---

## 📋 IMPLEMENTATION ROADMAP

### Phase 1: Procurement (Week 1-2)
1. Auto-PR from Low Stock
2. PO → Expected GR

### Phase 2: Production (Week 3-4)
3. Delivery Schedule → Auto PO
4. BOM Version Management

### Phase 3: HR (Week 5-6)
5. Leave Auto-Accrual
6. Employee Clearance Workflow

### Phase 4: Advanced (Week 7-8)
7. Bank Reconciliation Auto-Match
8. Stock Reservation System

---

## 🎯 QUICK WINS (Can implement in 1-2 days each)

### 1. Auto-PR from Low Stock
```php
// Add to CheckReorderPointsCommand.php
foreach ($lowStockItems as $item) {
    // Check if PR already exists for this item in draft/submitted
    $existingPr = PurchaseRequest::whereHas('items', fn($q) => 
        $q->where('item_description', 'like', "%{$item->name}%")
    )->whereIn('status', ['draft', 'submitted'])->exists();
    
    if (!$existingPr) {
        // Auto-create draft PR
        $this->prService->autoCreateForLowStock($item);
    }
}
```

### 2. Delivery Schedule → Production Order Link
```php
// In DeliveryScheduleService::update()
if ($data['status'] === 'confirmed' && $ds->type === 'manufacturing') {
    // Check if production order exists
    if ($ds->productionOrders()->count() === 0) {
        // Auto-create production order
        dispatch(new AutoCreateProductionOrderJob($ds));
    }
}
```

### 3. Leave Accrual Command
```php
// Create app/Console/Commands/AccrueLeaveBalances.php
// Schedule: monthlyOn(1, '02:00')
// Logic: Add monthly accrual based on employee tenure
```

---

## 💡 RECOMMENDED NEXT STEPS

1. **Immediate (This Week):**
   - Implement Auto-PR from Low Stock
   - Add Employee Clearance workflow

2. **Short Term (Next 2 Weeks):**
   - Delivery Schedule → Production Order automation
   - Leave auto-accrual

3. **Medium Term (Next Month):**
   - Stock reservation system
   - Bank reconciliation auto-matching

4. **Long Term (Next Quarter):**
   - Demand forecasting
   - Smart approval routing
   - Inter-company transactions

---

## 📊 EXPECTED IMPACT

| Enhancement | Time Saved | Error Reduction | User Satisfaction |
|-------------|------------|-----------------|-------------------|
| Auto-PR from Low Stock | 5 hrs/week | 90% | High |
| DS → Auto Production | 3 hrs/week | 80% | High |
| Employee Clearance | 2 hrs/employee | 95% | Medium |
| Leave Auto-Accrual | 8 hrs/month | 100% | Medium |
| Bank Auto-Reconcile | 10 hrs/week | 85% | High |
| **TOTAL** | **28 hrs/week** | **90% avg** | **High** |

---

*Document Generated: 2026-03-18*  
*For: Ogami ERP Enhancement Planning*  
*Priority: Implementation Ready*
