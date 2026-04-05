# Department-Scoped Permission Profile Management UI

## Context

The system has two layers of permission control:

1. **Global Role Permissions** (Spatie) -- managed by the new `/admin/roles-permissions` page
   - A `manager` role has ALL possible permissions assigned globally
2. **Department Permission Profiles** (`department_permission_profiles` table) -- **currently seeder-only**
   - Narrows which of those global permissions are effective for a specific Role + Department combination
   - e.g., HR + Manager = HR/payroll/recruitment permissions only
   - e.g., Accounting + Manager = GL/AP/AR/banking permissions only

The effective permission for a user is: **Global role permissions filtered by their department profile**.

### What exists already
- [`DepartmentPermissionProfile`](app/Models/DepartmentPermissionProfile.php) model with `department_id`, `role`, `permissions` JSON array, `profile_label`, `is_active`
- [`DepartmentPermissionService`](app/Services/DepartmentPermissionService.php) resolves effective permissions with 15-min caching
- [`DepartmentPermissionProfileSeeder`](database/seeders/DepartmentPermissionProfileSeeder.php) seeds ~815 lines of profiles
- 9 departments: HR, ACCTG, PROD, PURCH, IT, SALES, QC, MAINT, WH
- 4 department-scoped roles: `manager`, `officer`, `head`, `vice_president`

### What is missing
- No admin UI to view/edit department permission profiles
- No API endpoints for CRUD on `department_permission_profiles`
- No way to see what permissions an HR Manager actually gets without reading the seeder

---

## Design: Add Department Profiles Tab

The existing `/admin/roles-permissions` page gets two tabs:
1. **Global Permissions** -- current role-permission matrix (already built)
2. **Department Profiles** -- new tab for managing department+role permission profiles

### Department Profiles Tab Layout

- **Left panel**: List of all existing department+role profiles as clickable cards showing dept name, role, permission count, active/inactive badge
- **Top controls**: Department dropdown + Role dropdown to create/load a profile
- **Right panel**: Same grouped permission matrix component from Phase 1, reused
- **Active/Inactive toggle**: Each profile can be deactivated without deleting
- **Source guide**: Permissions that exist in the global role but NOT in this dept profile are shown dimmed, helping admin see what they could add
- **Create new profile**: Select a department+role combo that does not exist yet

---

## Backend API Endpoints

Add to [`routes/api/v1/admin.php`](routes/api/v1/admin.php). All require `system.assign_roles` permission.

### 1. List all department permission profiles
```
GET /api/v1/admin/department-profiles
```
Returns all profiles with department name, role, permission count, is_active.

### 2. Get a single profile detail
```
GET /api/v1/admin/department-profiles/{id}
```
Returns full profile with permissions array + department info.

### 3. Create or update a profile (upsert)
```
PUT /api/v1/admin/department-profiles
```
Body: `{ department_id, role, permissions, profile_label, is_active }`

Upserts based on unique (department_id, role) combination. Clears the DepartmentPermissionService cache after save.

### 4. Toggle active status
```
PATCH /api/v1/admin/department-profiles/{id}/toggle
```

### 5. Delete a profile
```
DELETE /api/v1/admin/department-profiles/{id}
```

### 6. List departments for dropdown
Already exists: reuse the admin departments endpoint.

---

## Frontend Changes

### Modified Files

| File | Change |
|------|--------|
| [`frontend/src/pages/admin/RolesPermissionsPage.tsx`](frontend/src/pages/admin/RolesPermissionsPage.tsx) | Add tab switcher; extract PermissionMatrix into reusable component; add DepartmentProfilesTab |
| [`frontend/src/hooks/useAdmin.ts`](frontend/src/hooks/useAdmin.ts) | Add hooks: `useDepartmentProfiles`, `useDepartmentProfileDetail`, `useUpsertDepartmentProfile`, `useToggleDepartmentProfile`, `useDeleteDepartmentProfile` |

### Reusable Permission Matrix
Extract the existing checkbox matrix into a shared component that accepts:
- `permissions: Set of string` (currently checked)
- `allPermissions: grouped object` (full list)
- `editMode: boolean`
- `onChange: callback`

Both the Global Permissions tab and Department Profiles tab use the same component.

---

## Cache Invalidation

After any profile change, call `DepartmentPermissionService::clearAllCaches()` on the backend to ensure the 15-minute cache is flushed. The frontend invalidates the relevant TanStack Query keys.

---

## Implementation Checklist

1. Add backend API endpoints for department profiles CRUD
2. Add audit logging for profile changes
3. Add frontend hooks for department profile management
4. Extract PermissionMatrix into a reusable component
5. Add tab switcher to RolesPermissionsPage (Global Permissions / Department Profiles)
6. Build DepartmentProfilesTab with left panel (profile list) and right panel (matrix)
7. Add department + role selector dropdowns for creating new profiles
8. Add active/inactive toggle UI
9. Test with HR+Manager, ACCTG+Manager, PROD+Head combinations
10. Clear DepartmentPermissionService cache on save
