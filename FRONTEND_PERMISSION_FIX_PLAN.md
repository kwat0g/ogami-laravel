# 🔐 Frontend Permission Guard Fix Plan (CRIT-002)

**Date:** 2026-03-15  
**Status:** Ready to Implement  
**Priority:** HIGH  
**Estimated Effort:** 3-4 hours

---

## 🎯 Scope

Add permission guards to self-service routes that currently have no protection:

| Route | Current State | Required Permission | Risk Level |
|-------|---------------|---------------------|------------|
| `/self-service/payslips` | No guard | `payslips.view` | Low |
| `/me/leaves` | No guard | `leaves.view_own` | Low |
| `/me/loans` | No guard | `loans.view_own` | Low |
| `/me/overtime` | No guard | `overtime.view` | Low |
| `/me/attendance` | No guard | `attendance.view_own` | Low |
| `/me/profile` | No guard | `self.view_profile` | Low |
| `/search` | No guard | ANY authenticated | Medium |

---

## 🔍 Current Router Structure

Based on AGENTS.md, routes are defined in `frontend/src/router/index.tsx` with a local `RequirePermission` guard component.

Example pattern:
```tsx
{ path: '/payroll/runs', element: withSuspense(guard('payroll.view', <PayrollRunsPage />)) },
```

---

## 📋 Implementation Tasks

### Task 1: Audit Current Self-Service Routes

Check `frontend/src/router/index.tsx` for self-service routes:
- `/self-service/*` routes
- `/me/*` routes
- `/search` route

### Task 2: Add Permission Guards

Update routes to use the `guard()` helper with appropriate permissions:

```tsx
// Before
{ path: '/me/leaves', element: withSuspense(<MyLeavesPage />) },

// After
{ path: '/me/leaves', element: withSuspense(guard('leaves.view_own', <MyLeavesPage />)) },
```

### Task 3: Create Missing Permission Constants (if needed)

Check `frontend/src/lib/permissions.ts` for:
- `payslips.view`
- `leaves.view_own`
- `loans.view_own`
- `overtime.view`
- `attendance.view_own`
- `self.view_profile`

Add any missing constants.

### Task 4: Handle "Any Authenticated" Case

For `/search`, use a generic authenticated guard:
```tsx
{ path: '/search', element: withSuspense(guardAuthenticated(<SearchPage />)) }
```

Or use `RequireAuth` component directly.

### Task 5: Test Routes

Verify each route:
1. Without authentication → Redirect to login
2. With authentication but no permission → Show "Access Denied"
3. With authentication and permission → Show page

---

## 🏗️ Technical Implementation

### Router Pattern

```tsx
// In frontend/src/router/index.tsx

// Helper function for permission guarding
function guard(permission: string, element: React.ReactNode) {
  return (
    <RequirePermission permission={permission}>
      {element}
    </RequirePermission>
  );
}

// Helper for authenticated-only routes (any logged-in user)
function guardAuthenticated(element: React.ReactNode) {
  return (
    <RequireAuth>
      {element}
    </RequireAuth>
  );
}
```

### Permission Constants

```typescript
// In frontend/src/lib/permissions.ts

export const PERMISSIONS = {
  // ... existing permissions
  
  // Self-service permissions
  PAYSIPS_VIEW: 'payslips.view',
  LEAVES_VIEW_OWN: 'leaves.view_own',
  LOANS_VIEW_OWN: 'loans.view_own',
  OVERTIME_VIEW: 'overtime.view',
  ATTENDANCE_VIEW_OWN: 'attendance.view_own',
  SELF_VIEW_PROFILE: 'self.view_profile',
} as const;
```

---

## 📁 Files to Modify

1. `frontend/src/router/index.tsx` - Add guards to routes
2. `frontend/src/lib/permissions.ts` - Add missing constants (if needed)

---

## ✅ Acceptance Criteria

- [ ] All self-service routes require authentication
- [ ] `/me/leaves` requires `leaves.view_own`
- [ ] `/me/loans` requires `loans.view_own`
- [ ] `/me/overtime` requires `overtime.view`
- [ ] `/me/attendance` requires `attendance.view_own`
- [ ] `/me/profile` requires `self.view_profile`
- [ ] `/self-service/payslips` requires `payslips.view`
- [ ] `/search` requires any authenticated user
- [ ] Unauthorized users see appropriate error/redirect
- [ ] Frontend typecheck passes
- [ ] Frontend lint passes

---

## 🚀 Next Steps

1. **Approve this plan** - Confirm scope and approach
2. **Read current router** - Examine existing patterns
3. **Implement guards** - Add permission checks to routes
4. **Test** - Verify routes behave correctly

Ready to proceed?
