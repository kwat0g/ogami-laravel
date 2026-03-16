# RBAC Scalability Analysis & Proposed Solution

## Current Architecture Problems

### 1. Hardcoded Roles (18 roles in code)
```php
// RolePermissionSeeder.php - Roles are hardcoded
$roles = [
    'super_admin', 'admin', 'executive', 'vice_president',
    'manager', 'plant_manager', 'production_manager', 'qc_manager',
    'officer', 'ga_officer', 'purchasing_officer', 'impex_officer',
    'head', 'staff', 'vendor', 'client', 'crm_manager', 'mold_manager'
];
```

**Problem**: Adding a new department position (e.g., "Warehouse Manager") requires:
1. Adding a new role to the seeder
2. Updating permission matrices
3. Updating frontend role checks
4. Testing all affected modules

### 2. Permission Assignment is Code-Based
```php
// Current: Permissions assigned in seeders
'manager' => [
    'employees.view', 'employees.create', ... // 50+ permissions hardcoded
]
```

**Problem**: Non-technical admins cannot grant permissions. Requires developer for every role change.

### 3. Department Scoping is Hardcoded
```php
// DepartmentPermissionServiceV3.php
$managerRoles = ['manager', 'officer', 'vice_president'];
$field = in_array($roleName, $managerRoles, true) 
    ? 'manager_permissions' 
    : 'supervisor_permissions';
```

**Problem**: Only two levels (manager/supervisor). No support for complex hierarchies.

### 4. Approval Workflows are Role-Based
```php
// Example: Loan approval chain
Step 1: staff (creates)
Step 2: head (approves)
Step 3: manager (checks)
Step 4: officer (reviews)
Step 5: vp (final)
```

**Problem**: Hardcoded chains. Cannot have custom workflows per department.

---

## Proposed Scalable Architecture

### Core Concept: **Permission-Centric + Dynamic Roles**

Instead of: `User → Role → Permissions`  
Move to: `User → Position → Role(s) → Permissions`

With: `Permission Templates` + `Workflow Definitions` (configurable, not coded)

---

## New Database Schema

```sql
-- 1. PERMISSIONS (existing, but extended)
permissions
    - id
    - name (e.g., 'employees.view')
    - group (e.g., 'hr', 'accounting', 'production')
    - description
    - requires_approval (boolean) -- SoD flag

-- 2. PERMISSION TEMPLATES (replaces hardcoded roles)
permission_templates
    - id
    - name (e.g., 'HR Manager', 'Accounting Officer')
    - description
    - permissions (JSON array)
    - level (integer) -- hierarchy level for approval routing
    - is_system (boolean) -- prevent deletion

-- 3. DEPARTMENTS (existing)
departments
    - id
    - code
    - name
    - default_template_id -- NEW: default permissions for dept

-- 4. POSITIONS (NEW - replaces static roles)
positions
    - id
    - department_id
    - template_id
    - title (e.g., 'Senior Accountant', 'HR Specialist')
    - reports_to_position_id -- self-referential hierarchy
    - approval_level (integer) -- for workflow routing
    - is_active

-- 5. USER_POSITIONS (NEW - users can have multiple positions)
user_positions
    - id
    - user_id
    - position_id
    - is_primary
    - started_at
    - ended_at

-- 6. WORKFLOW DEFINITIONS (NEW - configurable approval chains)
workflow_definitions
    - id
    - name (e.g., 'Loan Approval', 'Purchase Request')
    - entity_type (e.g., 'loan', 'purchase_request')
    - department_id (nullable -- null = global)
    - steps (JSON)
      [
        {"level": 1, "position_level_min": 2, "action": "submit"},
        {"level": 2, "position_level_min": 3, "action": "approve"},
        {"level": 3, "position_level_min": 5, "action": "final_approve"}
      ]
    - is_active

-- 7. WORKFLOW_INSTANCES (NEW - tracks actual approvals)
workflow_instances
    - id
    - definition_id
    - entity_type
    - entity_id
    - current_step
    - status
    - created_by_user_id
    - created_at
```

---

## Example: Adding a New Department (Warehouse)

### OLD WAY (requires code changes):
```php
// 1. Add to RolePermissionSeeder
'warehouse_manager' => [...permissions...],
'warehouse_officer' => [...permissions...],
'warehouse_head' => [...permissions...],
'warehouse_staff' => [...permissions...],

// 2. Add department permission profile
DepartmentPermissionProfile::create([
    'department_id' => $wh->id,
    'role' => 'warehouse_manager',
    'permissions' => [...]
]);

// 3. Update frontend role checks
// 4. Test everything
// 5. Deploy
```

### NEW WAY (admin UI only):
```sql
-- 1. Create Department (via admin UI)
INSERT INTO departments (code, name) VALUES ('WH', 'Warehouse');

-- 2. Create Permission Template (via admin UI)
INSERT INTO permission_templates (name, level, permissions) VALUES (
    'Warehouse Manager',
    4,
    '["inventory.items.view", "inventory.items.manage", "inventory.stock.view", ...]'
);

-- 3. Create Positions (via admin UI)
INSERT INTO positions (department_id, template_id, title, approval_level) VALUES
    (wh_id, wh_manager_template_id, 'Warehouse Manager', 4),
    (wh_id, wh_supervisor_template_id, 'Warehouse Supervisor', 3),
    (wh_id, staff_template_id, 'Warehouse Staff', 1);

-- 4. Assign Users to Positions (via admin UI)
INSERT INTO user_positions (user_id, position_id, is_primary) VALUES (...);

-- Done! No code changes, no deployment needed.
```

---

## Dynamic Permission Checking

### Current (hardcoded):
```php
// Controller
public function store(Request $request)
{
    $this->authorize('create', Employee::class);
    // Only checks if user has 'employees.create' permission
}
```

### Proposed (dynamic + context-aware):
```php
// Controller
public function store(Request $request)
{
    // Checks: 
    // 1. Does user have ANY position with 'employees.create'?
    // 2. Is the target department in user's department scope?
    // 3. Is this a SoD violation (user creating for themselves)?
    $this->authorize('create', [Employee::class, $request->department_id]);
}

// Or using a service:
public function store(Request $request)
{
    PermissionService::check(
        user: auth()->user(),
        permission: 'employees.create',
        context: ['department_id' => $request->department_id],
        sodCheck: true // prevent self-actions
    );
}
```

---

## Dynamic Approval Workflows

### Current (hardcoded in code):
```php
// LoanController
public function approve($id)
{
    $loan = Loan::find($id);
    
    if ($loan->status === 'pending_head') {
        $this->authorize('head_note', $loan); // Hardcoded step
        $loan->update(['status' => 'pending_manager']);
    } elseif ($loan->status === 'pending_manager') {
        $this->authorize('manager_check', $loan); // Hardcoded step
        // ...
    }
}
```

### Proposed (workflow-driven):
```php
// WorkflowService
public function transition(WorkflowInstance $instance, string $action, User $user)
{
    $definition = $instance->definition;
    $currentStep = $instance->current_step;
    $stepConfig = $definition->steps[$currentStep];
    
    // Check if user has sufficient position level for this step
    $userLevel = $user->highestPositionLevel();
    if ($userLevel < $stepConfig['position_level_min']) {
        throw new UnauthorizedException('Insufficient level');
    }
    
    // Check SoD: user cannot approve their own submission
    if ($instance->created_by_user_id === $user->id) {
        throw new SodViolationException();
    }
    
    // Advance workflow
    $instance->advance($action, $user->id);
}
```

### Workflow Definition Example (JSON):
```json
{
  "name": "Material Requisition",
  "entity_type": "material_requisition",
  "steps": [
    {"level": 1, "name": "Draft", "action": "submit", "auto": true},
    {"level": 2, "name": "Head Review", "min_position_level": 3, "action": "head_note"},
    {"level": 3, "name": "Manager Check", "min_position_level": 4, "action": "manager_check"},
    {"level": 4, "name": "VP Approval", "min_position_level": 5, "action": "vp_approve"}
  ]
}
```

---

## UI/UX Changes

### Admin Panel: Role & Permission Management

```
┌─────────────────────────────────────────────────────────────┐
│  RBAC Management                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  [Permission Templates] [Positions] [Workflows] [Audit]     │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Permission Templates                                │   │
│  │                                                     │   │
│  │ ┌──────────────────────────────────────────────┐   │   │
│  │ │ Template: HR Manager                         │   │   │
│  │ │ Level: 4 (Manager)                           │   │   │
│  │ │                                              │   │   │
│  │ │ Permissions:                                 │   │   │
│  │ │ ☑ employees.view        ☑ employees.create  │   │   │
│  │ │ ☑ employees.update      ☑ payroll.view      │   │   │
│  │ │ ☐ accounting.journal    ☐ accounting.ap     │   │   │
│  │ │                                              │   │   │
│  │ │ [Save] [Clone] [Delete]                      │   │   │
│  │ └──────────────────────────────────────────────┘   │   │
│  │                                                     │   │
│  │ [+ New Template]                                    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Position Management:
```
┌─────────────────────────────────────────────────────────────┐
│  Positions > Warehouse Department                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  [+ New Position]                                          │
│                                                             │
│  ┌──────────────┬────────────────┬──────────────┬─────────┐│
│  │ Title        │ Template       │ Level│Reports To│ Users ││
│  ├──────────────┼────────────────┼──────┼──────────┼───────┤│
│  │ WH Manager   │ Warehouse Mgr  │  4   │    VP    │   1   ││
│  │ WH Supervisor│ Warehouse Sup  │  3   │  Manager │   2   ││
│  │ WH Staff     │ Standard Staff │  1   │  Sup     │   5   ││
│  └──────────────┴────────────────┴──────┴──────────┴───────┘│
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Migration Path from Current System

### Phase 1: Dual Mode (Backward Compatible)
```php
// PermissionService.php
class PermissionService
{
    public function check(User $user, string $permission, array $context = []): bool
    {
        // NEW: Check dynamic positions first
        if ($this->hasDynamicPermission($user, $permission, $context)) {
            return true;
        }
        
        // FALLBACK: Check legacy Spatie roles
        return $user->hasPermissionTo($permission);
    }
}
```

### Phase 2: Migrate Existing Data
```php
// Migration command
php artisan rbac:migrate-to-v2

// What it does:
// 1. Creates permission templates from existing role definitions
// 2. Creates positions from existing user-department assignments
// 3. Links users to positions
// 4. Marks old role assignments as "legacy"
```

### Phase 3: Full Cutover
- Remove legacy role checks
- Admin manages everything via UI
- Developers only add new permission types (not roles)

---

## Benefits of New Architecture

| Aspect | Before | After |
|--------|--------|-------|
| **New Department** | Code changes + deploy | Admin UI only |
| **New Position** | Add to seeder + deploy | Admin UI only |
| **Permission Change** | Developer required | Admin can grant/revoke |
| **Approval Workflow** | Hardcoded | Configurable per dept |
| **Hierarchy** | 2 levels (manager/staff) | Unlimited levels |
| **Cross-Department** | Complex logic | Position-based |
| **Audit Trail** | Limited | Full workflow tracking |
| **SoD Enforcement** | Coded in policies | Configurable rules |

---

## Implementation Priority

### High Priority (Core Foundation)
1. `permission_templates` table + UI
2. `positions` table + UI
3. `user_positions` pivot table
4. `PermissionService` with dual-mode support

### Medium Priority (Workflow Engine)
5. `workflow_definitions` table
6. `workflow_instances` table
7. Workflow engine service
8. Generic approval UI components

### Low Priority (Nice to Have)
9. Permission analytics (who has access to what)
10. Role simulation (test permissions as user)
11. Approval delegation (temporarily grant approval authority)

---

## Code Example: New Permission Check

```php
// Before (current)
class EmployeeController extends Controller
{
    public function update(Request $request, Employee $employee)
    {
        // Hardcoded role check
        if (!auth()->user()->hasRole(['manager', 'admin'])) {
            abort(403);
        }
        
        // Hardcoded department check
        if ($employee->department_id !== auth()->user()->department_id) {
            abort(403);
        }
        
        // Hardcoded SoD check
        if ($employee->id === auth()->user()->employee_id) {
            abort(403, 'Cannot update own record');
        }
    }
}

// After (proposed)
class EmployeeController extends Controller
{
    public function update(Request $request, Employee $employee)
    {
        // Single call checks everything:
        // 1. Does user have 'employees.update' in ANY position?
        // 2. Is employee in user's department scope?
        // 3. Is user trying to update themselves (SoD)?
        $this->authorize('update', $employee);
    }
}

// Policy handles the complexity
class EmployeePolicy
{
    public function update(User $user, Employee $employee): bool
    {
        return PermissionService::check($user, 'employees.update', [
            'target_department_id' => $employee->department_id,
            'sod_check' => fn() => $employee->user_id === $user->id,
        ]);
    }
}
```

---

## Summary

The current RBAC system **works** but **doesn't scale** because:
- ❌ Roles are hardcoded
- ❌ Permissions require code changes
- ❌ New departments = new deployment
- ❌ Approval workflows are rigid

The proposed system **scales infinitely** because:
- ✅ Positions are data, not code
- ✅ Permission templates are configurable
- ✅ New departments via admin UI
- ✅ Dynamic approval workflows
- ✅ Hierarchy levels are numeric (1-10), not role names

**Bottom line**: With the new system, adding "Warehouse Manager" is a **5-minute admin task**, not a **2-hour development + deployment cycle**.
