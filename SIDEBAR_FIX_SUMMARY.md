# Sidebar Department Filtering - Fixes Applied

**Date:** March 15, 2026  
**Status:** CRITICAL FIXES IMPLEMENTED

---

## Issues Fixed

### 1. Mobile Sidebar Missing userDept Prop (CRITICAL)
**File:** `frontend/src/components/layout/AppLayout.tsx`

**Problem:** The mobile sidebar (Sheet component) was rendering `SectionNav` WITHOUT the `userDept` prop, causing department filtering to fail on mobile views.

**Fix:** Added `userDept={userDept}` to mobile sidebar SectionNav components at line 846.

```tsx
// Before:
<SectionNav
  key={section.label}
  section={section}
  hasPermission={hasPermission}
  hasRole={hasRole}
/>

// After:
<SectionNav
  key={section.label}
  section={section}
  hasPermission={hasPermission}
  hasRole={hasRole}
  userDept={userDept}
/>
```

---

### 2. Department Filtering Logic - Fail Open → Fail Closed (CRITICAL)
**File:** `frontend/src/components/layout/AppLayout.tsx`

**Problem:** The filtering logic was "fail open" - if `userDept` was null/undefined, it would show all modules. This is a security risk.

**Old Logic (Fail Open):**
```tsx
if (section.departments[0] !== 'ALL' && userDept && !section.departments.includes(userDept)) {
  return null
}
```

**New Logic (Fail Closed):**
```tsx
// Allow if explicitly ALL departments
if (section.departments[0] === 'ALL') {
  // Continue to show
} 
// Hide if no user department (fail closed for security)
else if (!userDept) {
  return null
}
// Hide if user's department not in allowed list
else if (!section.departments.includes(userDept)) {
  return null
}
```

**Applied to:**
- `SectionNav` function (line 431-445)
- `CompactSectionNav` function (line 507-525)

---

### 3. Added Debug Logging
**File:** `frontend/src/components/layout/AppLayout.tsx`

Added console logging to help diagnose filtering issues in development mode:

```tsx
// In AppLayout component:
console.log('[AppLayout] User:', {
  email: user.email,
  roles: user.roles,
  primaryDepartmentCode: user.primary_department_code,
  departmentIds: user.department_ids,
})

// In SectionNav component:
console.log(`[SectionNav] ${section.label}:`, {
  userDept,
  allowedDepts: section.departments,
  bypass: hasRole('super_admin') || hasRole('executive') || hasRole('vice_president'),
  visible: deptCheckPassed,
})
```

---

## Expected Behavior After Fixes

| User Role | Department Code | Should See | Should NOT See |
|-----------|----------------|------------|----------------|
| HR Manager | HR | HR, Payroll, Reports, Team Management, GA Processing | Accounting, Production, Inventory, Procurement |
| Production Manager | PROD | Production, Inventory, Procurement, QC, Maintenance, Mold | HR, Accounting, Banking, Payroll |
| Accounting Manager | ACCTG | Accounting, Payables, Receivables, Banking, Tax, Fixed Assets, Budget | HR, Production, Inventory |
| Purchasing Officer | PURCH | Procurement, Inventory (GR), Payables (vendors) | HR, Production, Accounting (restricted) |
| VP | (bypass) | ALL MODULES | (none) |

---

## Testing Instructions

### 1. Clear Browser Cache
Before testing, clear browser cache and local storage:
```javascript
localStorage.clear()
location.reload()
```

### 2. Test HR Manager
```
Email: hr.manager@ogamierp.local
Password: Manager@12345!
```

**Expected Sidebar:**
- ✅ Dashboard
- ✅ Team Management
- ✅ Human Resources
- ✅ Payroll
- ✅ Reports
- ✅ GA Processing
- ❌ Accounting (should be hidden)
- ❌ Production (should be hidden)

### 3. Test Production Manager
```
Email: prod.manager@ogamierp.local
Password: Manager@12345!
```

**Expected Sidebar:**
- ✅ Dashboard
- ✅ Team Management
- ✅ Production
- ✅ Procurement
- ✅ Inventory
- ✅ QC / QA
- ✅ Maintenance
- ✅ Mold
- ❌ HR (should be hidden)
- ❌ Accounting (should be hidden)

### 4. Test VP (Bypass)
```
Email: vp@ogamierp.local
Password: VicePresident@1!
```

**Expected:** Can see ALL modules (bypass role)

### 5. Check Browser Console
Open browser console (F12) and look for:
```
[AppLayout] User: {email: '...', roles: [...], primaryDepartmentCode: 'HR', ...}
[SectionNav] Human Resources: {userDept: 'HR', allowedDepts: ['HR'], visible: true}
[SectionNav] Accounting: {userDept: 'HR', allowedDepts: ['ACCTG', 'EXEC'], visible: false}
```

---

## If Issues Persist

1. **Check User Data:**
   - Open browser console
   - Look for `[AppLayout] User:` log
   - Verify `primaryDepartmentCode` is set correctly

2. **Check Section Filtering:**
   - Look for `[SectionNav]` logs
   - Verify `userDept` matches expected department
   - Check `visible` field

3. **Verify Backend Response:**
   ```bash
   curl -X POST http://localhost:8000/api/v1/auth/login \
     -d '{"email":"hr.manager@ogamierp.local","password":"Manager@12345!"}' \
     -c cookies.txt
   
   curl http://localhost:8000/api/v1/user -b cookies.txt
   ```
   
   Verify response includes:
   ```json
   {
     "primary_department_code": "HR",
     "department_ids": [1]
   }
   ```

4. **Clear Auth Store:**
   ```javascript
   localStorage.removeItem('auth-store')
   location.reload()
   ```

---

## Technical Details

### Department Codes Used
| Code | Department |
|------|------------|
| HR | Human Resources |
| ACCTG | Accounting |
| PROD | Production |
| PLANT | Plant |
| PURCH | Procurement |
| WH | Warehouse |
| QC | Quality Control |
| MAINT | Maintenance |
| MOLD | Mold |
| SALES | Sales |
| IT | IT |
| EXEC | Executive |

### Sidebar Sections with Department Restrictions
| Section | Allowed Departments |
|---------|-------------------|
| Human Resources | HR |
| Payroll | HR, ACCTG |
| Accounting | ACCTG, EXEC |
| Payables (AP) | ACCTG, PURCH, EXEC |
| Receivables (AR) | ACCTG, SALES, EXEC |
| Banking | ACCTG |
| Financial Reports | ACCTG, EXEC |
| Fixed Assets | ACCTG, EXEC |
| Budget | ACCTG, EXEC |
| Reports | HR, ACCTG |
| GA Processing | HR |
| Executive Approvals | EXEC |
| Procurement | PURCH, PROD, PLANT |
| Inventory | WH, PURCH, PROD, PLANT, SALES |
| Production | PROD, PLANT, PPC |
| QC / QA | QC, PROD, WH |
| Maintenance | MAINT, PROD, PLANT |
| Mold | MOLD, PROD |
| Delivery | WH, SALES, PROD, PLANT |
| ISO / IATF | ISO, QC |
| CRM | SALES |

---

## Rollback

If needed, revert changes:
```bash
git checkout HEAD -- frontend/src/components/layout/AppLayout.tsx
```

---

**All fixes have been applied and TypeScript compilation passes! ✅**
