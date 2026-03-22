# Ogami ERP - Workflow Quick Reference

## 1. Purchase Request (PR) Approval Workflow

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  Create PR      │     │  Dept Head       │     │  VP Approval    │
│  (Procurement   │────▶│  Approval        │────▶│  (>100k)        │
│   Officer)      │     │                  │     │                 │
└─────────────────┘     └──────────────────┘     └─────────────────┘
        │                       │                       │
        ▼                       ▼                       ▼
   Status: DRAFT           Status: PENDING        Status: VP_
                                                   APPROVED
```

**Roles & Actions:**
| Step | Role | Can Create | Can Approve | Can Reject |
|------|------|------------|-------------|------------|
| 1 | Purchasing Officer | ✅ | ❌ (SoD) | ❌ |
| 2 | Department Head | ❌ | ✅ | ✅ |
| 3 | VP (>100k) | ❌ | ✅ | ✅ |

**Test Steps:**
1. Login as `purchasing.officer@ogamierp.local`
2. Create PR → Status: DRAFT
3. Try approve → Should FAIL (SoD)
4. Login as `production.head@ogamierp.local`
5. Approve PR → Status: HEAD_APPROVED
6. If amount >100k, login as `vp@ogamierp.local`
7. Approve PR → Status: VP_APPROVED

---

## 2. Purchase Order (PO) Creation Workflow

```
┌─────────────────┐     ┌──────────────────┐
│  Select Approved│     │  Add Vendor &    │
│  PR             │────▶│  Submit PO       │
│                 │     │                  │
└─────────────────┘     └──────────────────┘
```

**Who Can Create:** Purchasing Officer, Procurement Manager
**Who Can Approve:** Department Head, Plant Manager (depending on amount)

---

## 3. Payroll Processing Workflow

```
┌─────────────┐   ┌─────────────┐   ┌─────────────┐   ┌─────────────┐
│ HR Manager  │──▶│ Submit for  │──▶│ VP Approves │──▶│ Accounting  │
│ Computes    │   │ Approval    │   │ Payroll     │   │ Posts to GL │
│ Payroll     │   │             │   │             │   │             │
└─────────────┘   └─────────────┘   └─────────────┘   └─────────────┘
      │                  │                  │                  │
      ▼                  ▼                  ▼                  ▼
 Status:            Status:           Status:           Journal
 COMPUTED           SUBMITTED         VP_APPROVED       Entries
                                                         Created
```

**Roles & Actions:**
| Step | Role | Action | SoD Check |
|------|------|--------|-----------|
| 1 | HR Manager | Create & Compute | N/A |
| 2 | HR Manager | Submit | Cannot approve own |
| 3 | VP | Approve | Must be different person |
| 4 | Accounting | Post to GL | Different dept |

**Test Steps:**
1. Login as `hr.manager@ogamierp.local`
2. Go to Payroll → Runs → New
3. Select period, compute
4. Submit for approval
5. Login as `vp@ogamierp.local`
6. Go to VP Approvals → Payroll
7. Approve payroll
8. Login as `acctg.manager@ogamierp.local`
9. Post to General Ledger

---

## 4. Leave Request Workflow

```
┌─────────────┐   ┌─────────────┐   ┌─────────────┐
│ Employee    │──▶│ Manager     │──▶│ HR Records  │
│ Submits     │   │ Approves    │   │ Leave       │
│ Request     │   │             │   │             │
└─────────────┘   └─────────────┘   └─────────────┘
      │                  │                  │
      ▼                  ▼                  ▼
 Status:            Status:           Balance
 PENDING            APPROVED          Updated
```

**Test Steps:**
1. Login as `prod.staff@ogamierp.local`
2. Self Service → Leave → New Request
3. Submit
4. Login as `prod.manager@ogamierp.local`
5. Leave → Team Requests
6. Approve request
7. Login as `hr.manager@ogamierp.local`
8. Verify leave balance updated

---

## 5. Production Order Workflow

```
┌─────────────┐   ┌─────────────┐   ┌─────────────┐   ┌─────────────┐
│ Create      │──▶│ Add BOM &   │──▶│ Manager/    │──▶│ Release to  │
│ Order       │   │ Materials   │   │ Plant Mgr   │   │ Production  │
│             │   │             │   │ Approves    │   │             │
└─────────────┘   └─────────────┘   └─────────────┘   └─────────────┘
      │                  │                  │                  │
      ▼                  ▼                  ▼                  ▼
 Status:            Status:           Status:           Status:
 DRAFT              DRAFT             APPROVED          RELEASED
```

**Who Can Create:** Production Manager, Production Head
**Who Can Approve:** Production Manager (own), Plant Manager (if policy requires)

**Test Steps:**
1. Login as `prod.manager@ogamierp.local`
2. Production → Orders → New
3. Fill product, quantity, target date
4. Select BOM
5. Submit/Save
6. Approve (if SoD allows self-approval)
7. Release to production

---

## 6. Journal Entry Workflow

```
┌─────────────┐   ┌─────────────┐   ┌─────────────┐
│ Accounting  │──▶│ Submit for  │──▶│ VP/Manager  │
│ Creates     │   │ Approval    │   │ Approves    │
│ Entry       │   │             │   │ (if req'd)  │
└─────────────┘   └─────────────┘   └─────────────┘
      │                  │                  │
      ▼                  ▼                  ▼
 Status:            Status:           Status:
 DRAFT              PENDING           POSTED
```

**Who Can Create:** Accounting Manager, Accounting Officer (limited)
**Who Can Approve:** Accounting Manager, VP (for high-value entries)

---

## 7. Goods Receipt Workflow (GRN)

```
┌─────────────┐   ┌─────────────┐   ┌─────────────┐
│ Warehouse   │──▶│ Receive     │──▶│ Inventory   │
│ Receives    │   │ Goods       │   │ Updated     │
│ PO          │   │             │   │             │
└─────────────┘   └─────────────┘   └─────────────┘
        │                                       │
        ▼                                       ▼
   Notify AP                              Stock Ledger
   (Invoice                               Updated
   Matching)
```

**Who Can Receive:** Warehouse Head, Warehouse Staff
**Who Can Verify:** QC (if inspection required)

---

## 8. Vendor Invoice Processing Workflow

```
┌─────────────┐   ┌─────────────┐   ┌─────────────┐   ┌─────────────┐
│ Receive     │──▶│ Match to    │──▶│ Accounting  │──▶│ Payment     │
│ Invoice     │   │ PO/GRN      │   │ Approves    │   │ Processed   │
│             │   │             │   │             │   │             │
└─────────────┘   └─────────────┘   └─────────────┘   └─────────────┘
      │                  │                  │                  │
      ▼                  ▼                  ▼                  ▼
   AP Officer       3-Way Match       Status:            Status:
   Records          Validation        APPROVED           PAID
```

**Who Can Record:** AP Officer, Accounting Officer
**Who Can Approve:** Accounting Manager
**Who Can Pay:** Accounting Manager (or authorized officer)

**SoD:** Person who records invoice should not approve payment

---

## 9. Fixed Asset Acquisition Workflow

```
┌─────────────┐   ┌─────────────┐   ┌─────────────┐   ┌─────────────┐
│ Create      │──▶│ Department  │──▶│ Accounting  │──▶│ Asset       │
│ Asset       │   │ Head        │   │ Capitalizes │   │ Added to    │
│ Request     │   │ Approves    │   │ & Records   │   │ Register    │
└─────────────┘   └─────────────┘   └─────────────┘   └─────────────┘
```

**Who Can Request:** Department Manager
**Who Can Approve:** Department Head, VP (if over budget)
**Who Can Capitalize:** Accounting Manager

---

## 10. QC Inspection Workflow

```
┌─────────────┐   ┌─────────────┐   ┌─────────────┐   ┌─────────────┐
│ Production  │──▶│ QC          │──▶│ If Failed:  │──▶│ NCR         │
│ Completes   │   │ Inspects    │   │ Create NCR  │   │ Resolution  │
│ Batch       │   │             │   │             │   │             │
└─────────────┘   └─────────────┘   └─────────────┘   └─────────────┘
                          │
                          ▼ (If Passed)
                   Release Batch
                   Update Inventory
```

**Who Triggers:** Production staff
**Who Inspects:** QC Officer, QC Manager
**Who Creates NCR:** QC Manager
**Who Resolves NCR:** Production Manager, QC Manager (collaboration)

---

## Approval Limits Matrix

| Role | PR Approval | PO Approval | Payroll | Journal Entry | Leave |
|------|-------------|-------------|---------|---------------|-------|
| Staff | ❌ | ❌ | ❌ | ❌ | Create only |
| Head | Up to 50k | Up to 50k | ❌ | ❌ | Team only |
| Manager | Up to 100k | Up to 100k | ❌ | ❌ | Team only |
| VP | Unlimited | Unlimited | ✅ | ✅ | ❌ |
| Accounting Mgr | ❌ | ❌ | Post only | Unlimited | ❌ |

---

## SoD (Segregation of Duties) Rules

1. **Cannot approve own requests:**
   - Purchasing Officer cannot approve own PR
   - HR Manager cannot approve own leave
   - Any user cannot approve their own expense claim

2. **Creator vs Approver:**
   - PR Creator ≠ PR Approver
   - PO Creator ≠ PO Approver (for large amounts)
   - Invoice Recorder ≠ Payment Approver

3. **Department Separation:**
   - Payroll (HR) ≠ Accounting (GL Posting)
   - Procurement ≠ AP Payment
   - Production ≠ QC Inspection

---

## Quick Workflow Test Commands

### Test PR Workflow
```bash
# Step 1: Create PR
curl -X POST http://localhost:8000/api/v1/procurement/purchase-requests \
  -H "Authorization: Bearer TOKEN" \
  -d '{"department_id": 4, "items": [...]}'

# Step 2: Approve PR
curl -X POST http://localhost:8000/api/v1/procurement/purchase-requests/123/approve \
  -H "Authorization: Bearer TOKEN"
```

### Test Payroll Workflow
```bash
# Step 1: Create Payroll Run
curl -X POST http://localhost:8000/api/v1/payroll/runs \
  -H "Authorization: Bearer TOKEN" \
  -d '{"period": "2026-03-01", "type": "semi-monthly"}'

# Step 2: Submit for Approval
curl -X POST http://localhost:8000/api/v1/payroll/runs/123/submit \
  -H "Authorization: Bearer TOKEN"

# Step 3: VP Approve
curl -X POST http://localhost:8000/api/v1/payroll/runs/123/vp-approve \
  -H "Authorization: Bearer TOKEN"
```
