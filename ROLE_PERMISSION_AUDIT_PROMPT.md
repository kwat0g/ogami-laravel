# 🔐 OGAMI ERP — ROLE & PERMISSION COMPLETENESS AUDIT PROMPT

> **How to use this prompt:**
> Paste this into any AI (Claude, Cursor, ChatGPT, etc.) alongside your full codebase.
> The AI will discover every role, every permission, and every frontend page/component
> entirely from your code — no assumptions, no invented roles, no invented permissions.
> Everything it audits must come from evidence it finds in your system.

---

## INSTRUCTIONS TO THE AI

You are a **senior security engineer and UX architect** performing a complete
**role and permission completeness audit** on this ERP system.

Your job is divided into four phases:

**Phase 1 — Discovery:** Find every role and every permission that exists in the system.
**Phase 2 — Mapping:** For every role, map what the system currently allows vs. what
that role's job responsibilities actually require based on evidence in the codebase.
**Phase 3 — Frontend Audit:** Verify that every frontend page, action button, form,
and navigation item correctly matches the permission system — nothing shown that
shouldn't be, nothing hidden that should be accessible.
**Phase 4 — Gap & Risk Report:** Identify every mismatch, missing permission,
overprivileged role, underprivileged role, and security risk.

### Prime Directive: Discover Everything — Assume Nothing

- Do NOT invent roles. Only work with roles you find in the codebase.
- Do NOT invent permissions. Only work with permissions you find defined in the system.
- Do NOT assume what a role "should" have based on generic ERP knowledge —
  base all evaluations on the actual responsibilities evident from the codebase.
- If a role's name implies a responsibility but the codebase shows no feature for it,
  flag it as a question — do not invent gaps.
- Every finding must cite the exact file, line, or config where you found the evidence.

---

---

# PHASE 1 — FULL DISCOVERY

---

## Step 1.1 — Find All Roles

Search the entire codebase for role definitions. Look in all of the following locations
and any others you discover:

- Permission/role seeder files (e.g., `database/seeders/`)
- Role and permission config files
- Migration files that create roles or permissions tables
- Any file that calls `Role::create()`, `Role::findOrCreate()`, or equivalent
- Any file that calls `assignRole()`, `givePermissionTo()`, or equivalent
- Any constants file that defines role name strings
- Any enum or value object representing roles
- Any middleware that checks roles (role names often appear as strings here)
- Any policy file that references role names
- Any frontend file that checks roles (e.g., `user.role === '...'`, `hasRole('...')`)
- Any navigation/sidebar config that gates items by role
- Any route middleware that restricts by role
- Any test file that sets up users with specific roles

For every role found, record:

```
ROLE DISCOVERED
═══════════════════════════════════════════════════
Name (as stored in DB):     [exact string value]
Display name (if different): [human-readable label]
Found in:                   [file path(s) and line numbers]
Also referenced in:         [all other files that mention this role]
Guard:                      [web | api | both]
Is it a super-admin role?   [yes / no / uncertain]
Is it a department-scoped role? [yes / no / uncertain]
Notes:                      [anything unusual about how this role is used]
═══════════════════════════════════════════════════
```

---

## Step 1.2 — Find All Permissions

Search the entire codebase for permission definitions. Look in all of the following:

- Permission seeder files
- Any file that calls `Permission::create()` or equivalent
- Any constants or enum that lists permission strings
- Any policy class (`app/Policies/`)
- Any `Gate::define()` or `Gate::allows()` calls
- Any middleware that checks specific permissions
- Any controller or service that calls `authorize()`, `can()`, `hasPermissionTo()`
- Any frontend file that checks permissions (e.g., `can('permission.name')`,
  `usePermission()`, `hasPermission()`)
- Any route definition with `middleware('can:...')`

For every permission found, record:

```
PERMISSION DISCOVERED
═══════════════════════════════════════════════════
Name (exact string):        [e.g., hr.employees.view]
Found in (definition):      [seeder or config file + line]
Also used in:               [controllers, policies, frontend files that check this]
Naming convention:          [does it follow module.resource.action pattern?]
Assigned to roles:          [which roles have this permission in the seeder]
═══════════════════════════════════════════════════
```

---

## Step 1.3 — Find All Permission Checks in the Backend

Scan every controller, service, policy, middleware, and route for permission checks:

- `$this->authorize('permission.name')`
- `auth()->user()->can('permission.name')`
- `$user->hasPermissionTo('permission.name')`
- `middleware('can:permission.name')`
- `Gate::allows('permission.name')`
- Policy methods that map to permissions

For each check found:

```
BACKEND PERMISSION CHECK
  Location:    [file path + method name + line number]
  Permission:  [exact permission string being checked]
  Context:     [what action this protects]
  Check type:  [middleware | authorize() | can() | policy | gate]
```

---

## Step 1.4 — Find All Permission Checks in the Frontend

Scan every frontend file for permission and role checks:

- Any component, hook, or utility that checks permissions
- Sidebar/navigation config files that hide items by permission or role
- Route guards or protected route wrappers
- Conditional rendering based on permissions (`{can('x') && <Button/>}`)
- Any `v-if`, `ng-if`, JSX conditional, or ternary that references a permission or role

For each check found:

```
FRONTEND PERMISSION CHECK
  File:        [path to file]
  Component:   [component or page name]
  Permission:  [exact permission or role string being checked]
  Element:     [what UI element is shown/hidden — button, page, tab, field]
  Check type:  [route guard | nav hide | conditional render | field disable]
```

---

## Step 1.5 — Find All Frontend Pages and Actions

Before you can audit role-page matching, you need a complete map of the frontend.

Scan all frontend route definitions, page components, and navigation configs:

For every page/screen in the frontend:

```
FRONTEND PAGE
  Route path:          [e.g., /hr/employees]
  Component file:      [path to the page component]
  Navigation section:  [which sidebar group it belongs to]
  Permission guard:    [what permission or role is required to access it, if any]
  Actions available:   [list every button/action on this page and its permission guard]
    - Create button:   [permission checked, if any]
    - Edit button:     [permission checked, if any]
    - Delete button:   [permission checked, if any]
    - Approve button:  [permission checked, if any]
    - Export button:   [permission checked, if any]
    - [other actions]: [permission checked, if any]
  Data sensitivity:    [does this page show sensitive data like salaries, IDs, etc.?]
```

---

---

# PHASE 2 — ROLE RESPONSIBILITY MAPPING

---

For each role discovered in Phase 1, perform a deep analysis.

Do not define what the role "should" do based on generic knowledge.
Instead, **infer the role's intended responsibilities from the codebase itself** by:

- What permissions are assigned to this role in the seeder
- What navigation items are visible for this role
- What dashboards are configured for this role
- What workflow steps are assigned to this role (approvals, reviews, etc.)
- What notifications are sent to this role
- What reports are accessible to this role
- What the role name and any comments/docs say about it

Then evaluate: **do the permissions actually match the evident responsibilities?**

---

## Per-Role Audit Template

For each role, produce this full evaluation:

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: [Role Name]                                             ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: [seeder file + line]                               ║
║  GUARD: [web/api]                                                    ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  Based on permissions, dashboard, navigation, and workflow position,
  this role is responsible for:
  - [Responsibility 1 — cite evidence: what permission or feature reveals this]
  - [Responsibility 2 — cite evidence]
  - [Responsibility 3 — cite evidence]
  ...

PERMISSIONS CURRENTLY ASSIGNED: [total count]
  List every permission this role has, grouped by module:

  [Module Name]:
    ✅ [permission.name] — [what this allows]
    ✅ [permission.name] — [what this allows]

  [Next Module]:
    ✅ [permission.name] — [what this allows]

FRONTEND ACCESS — PAGES THIS ROLE CAN SEE:
  List every frontend page accessible to this role, with the permission that grants it:
  - [/page/path] — via [permission.name]
  - [/page/path] — via [permission.name]

FRONTEND ACCESS — ACTIONS THIS ROLE CAN PERFORM:
  List every action button/form action this role can use:
  - [Page] → [Action] — via [permission.name]
  - [Page] → [Action] — via [permission.name]

WORKFLOW POSITIONS:
  List every approval step, review step, or workflow action this role can take:
  - [Module] [Workflow] — [Step name] — [how this is enforced: permission or role check]

NOTIFICATIONS RECEIVED:
  List every notification type sent to this role:
  - [Notification class] — [trigger event]

DASHBOARDS:
  - Dashboard file/component for this role: [path]
  - KPIs shown: [list]

─────────────────────────────────────────────────────────────────────
GAPS FOUND — MISSING PERMISSIONS (role needs this but doesn't have it)
─────────────────────────────────────────────────────────────────────

For each gap: only flag it if there is evidence the role needs this access
based on its workflow position, dashboard, or related permissions.

  ❌ MISSING: [permission.name]
     Why needed: [specific evidence — e.g., "this role approves Leave requests
                  at step 2, but cannot view Leave balances to make informed decisions"]
     Risk if absent: [what breaks or what workaround users are forced into]
     Recommended fix: [add permission X to this role in the seeder]

─────────────────────────────────────────────────────────────────────
GAPS FOUND — EXCESS PERMISSIONS (role has this but shouldn't)
─────────────────────────────────────────────────────────────────────

  ⚠️ EXCESS: [permission.name]
     Why it shouldn't be here: [specific evidence — e.g., "this role has
                                 payroll.delete but no evidence it should
                                 be able to delete payroll runs"]
     Risk: [what this permission allows that could be abused]
     Recommended fix: [remove permission X from this role in the seeder]

─────────────────────────────────────────────────────────────────────
FRONTEND MISMATCHES
─────────────────────────────────────────────────────────────────────

  🔴 PAGE ACCESSIBLE BUT PERMISSION NOT ASSIGNED:
     Page: [path]
     Issue: [the route/component doesn't check a permission, so all roles can see it]
     Fix: [add permission check to route guard and/or component]

  🔴 PAGE INACCESSIBLE BUT ROLE NEEDS IT:
     Page: [path]
     Issue: [the permission exists and is assigned to this role, but the frontend
             doesn't expose the navigation link or the route guard blocks it incorrectly]
     Fix: [add nav entry / fix route guard for this role]

  🟡 ACTION VISIBLE BUT NOT BACKED BY PERMISSION CHECK:
     Page: [path] → Action: [button name]
     Issue: [button renders for all users, no permission check on the frontend]
     Fix: [wrap button in permission check for permission.name]

  🟡 ACTION HIDDEN BUT ROLE HAS THE PERMISSION:
     Page: [path] → Action: [button name]
     Issue: [role has the permission but the frontend hides the button from this role]
     Fix: [check frontend conditional — permission string may be mismatched or typo]

─────────────────────────────────────────────────────────────────────
SEPARATION OF DUTIES VIOLATIONS
─────────────────────────────────────────────────────────────────────

  Flag any case where a single role can both initiate AND approve the same transaction.
  This is a critical internal control violation in financial and HR systems.

  🔴 SOD VIOLATION: [describe the violation]
     Role: [role name]
     Can create: [permission.name] → [what it creates]
     Can also approve: [permission.name] → [what it approves]
     Risk: [why this is a control weakness — e.g., "can create a vendor invoice and
             approve payment on the same invoice — funds could be embezzled"]
     Fix: [how to split this — which permission to remove and assign to a different role]

─────────────────────────────────────────────────────────────────────
OVERALL ROLE HEALTH
─────────────────────────────────────────────────────────────────────

  Missing permissions:     [count]
  Excess permissions:      [count]
  Frontend mismatches:     [count]
  SOD violations:          [count]
  Role health score:       [X/100]
  Summary:                 [2-sentence assessment]
```

---

---

# PHASE 3 — SYSTEM-WIDE FRONTEND AUDIT

---

Beyond per-role analysis, audit the entire frontend for systemic permission problems.

## Step 3.1 — Unprotected Pages

Find every frontend page/route that has NO permission or role check:

```
UNPROTECTED PAGE
  Route:       [path]
  Component:   [file]
  Risk level:  🔴 High | 🟠 Medium | 🟡 Low
  Reason:      [what sensitive data or action is exposed]
  Fix:         [which permission should guard this page]
```

## Step 3.2 — Unprotected Actions

Find every action button, form submit, or destructive action that has NO permission check
on the frontend (even if the backend checks it):

```
UNPROTECTED ACTION
  Page:        [route path]
  Element:     [button/form/link description]
  Action:      [what it does — delete, approve, export, etc.]
  Backend protected? [yes / no / unknown]
  Fix:         [which permission should gate this element]
```

Note: backend protection alone is insufficient — showing a button that returns 403
creates bad UX and reveals system structure to unauthorized users.

## Step 3.3 — Navigation Visibility vs. Permission Reality

For every navigation item in the sidebar or menu:

```
NAV ITEM CHECK
  Label:              [display name in sidebar]
  Route:              [path it links to]
  Nav gated by:       [permission or role check on the nav item, if any]
  Page gated by:      [permission check on the actual page component]
  Are they in sync?   [yes / no / mismatch]
  Mismatch detail:    [e.g., "nav item hidden for role X but the page itself has no guard,
                        so direct URL access still works"]
```

## Step 3.4 — Permission String Consistency

Find any permission strings in the frontend that do NOT match the permission strings
defined in the backend seeder (typos, case differences, old names, deprecated strings):

```
PERMISSION STRING MISMATCH
  Frontend string:    [what the frontend checks]
  Backend string:     [what is actually defined in the seeder]
  File:               [frontend file + line]
  Impact:             [permission check always fails/passes silently]
  Fix:                [correct the string to match the canonical definition]
```

## Step 3.5 — Role-Specific Dashboard Completeness

For each role that has a dedicated dashboard:

```
DASHBOARD AUDIT
  Role:               [role name]
  Dashboard file:     [path to component]
  KPIs shown:         [list all metrics displayed]
  Permissions needed: [does the dashboard check permissions before showing each KPI?]
  Missing KPIs:       [based on this role's responsibilities, what data should be
                       visible that is currently absent from the dashboard?]
  Excess KPIs:        [any data shown that this role has no business seeing?]
  Data sensitivity:   [does the dashboard expose financial figures, headcount, or PII?]
  Access control:     [is the dashboard route properly gated?]
```

## Step 3.6 — Self-Service & Portal Pages

For any employee self-service pages, vendor portal, or client portal:

```
PORTAL PAGE AUDIT
  Portal type:        [employee self-service / vendor portal / client portal]
  Route:              [path]
  What it shows:      [description of content]
  Scoping check:      [can a user see OTHER users' data? e.g., can employee A
                       see employee B's payslips by changing a URL parameter?]
  Authorization type: [permission check / ownership check / department scope / none]
  Risk:               [describe any unauthorized data exposure risk]
  Fix:                [what check needs to be added]
```

---

---

# PHASE 4 — FINAL AUDIT OUTPUTS

---

## Output 1 — Complete Role × Permission Matrix

Produce a full matrix showing every role vs. every permission.
Group permissions by module. Use ✅ for assigned, ❌ for not assigned.

This is the ground truth of what your system currently allows.

```
| Permission                    | [Role 1] | [Role 2] | [Role 3] | [Role N] |
|-------------------------------|----------|----------|----------|----------|
| MODULE: [module name]         |          |          |          |          |
| module.resource.view          |  ✅      |  ✅      |  ❌      |  ✅      |
| module.resource.create        |  ✅      |  ❌      |  ❌      |  ✅      |
| module.resource.edit          |  ✅      |  ❌      |  ❌      |  ✅      |
| module.resource.delete        |  ❌      |  ❌      |  ❌      |  ✅      |
| module.resource.approve       |  ❌      |  ✅      |  ❌      |  ✅      |
| ...                           |          |          |          |          |
```

After the matrix, highlight:
- Any column (role) with suspiciously many ✅ (possibly over-privileged)
- Any column (role) with suspiciously few ✅ (possibly under-privileged or incomplete)
- Any row (permission) with no ✅ at all (defined but assigned to no role — orphan permission)
- Any row (permission) with ✅ on every role (effectively a public permission — is that intended?)

---

## Output 2 — Master Gap Register

All findings in one prioritized table:

```
| # | Type              | Role      | Module     | Finding                        | Risk    | Fix                            |
|---|-------------------|-----------|------------|--------------------------------|---------|--------------------------------|
| 1 | Missing Permission| [role]    | [module]   | Cannot view X needed for Y     | 🔴 High | Add permission X to role       |
| 2 | Excess Permission | [role]    | [module]   | Can delete Z with no reason    | 🟠 Med  | Remove permission Z from role  |
| 3 | SOD Violation     | [role]    | [module]   | Creates and approves invoices  | 🔴 High | Split approval to another role |
| 4 | Frontend Unguarded| —         | [module]   | /path has no permission check  | 🟠 Med  | Add route guard for permission |
| 5 | Permission Orphan | —         | [module]   | Permission X assigned to no one| 🟡 Low  | Assign to correct role or remove|
| 6 | String Mismatch   | —         | [module]   | Frontend checks wrong string   | 🟠 Med  | Fix typo in frontend check     |
```

---

## Output 3 — Role Health Scorecard

```
| Role          | Permissions | Missing | Excess | SOD Violations | FE Mismatches | Score |
|---------------|-------------|---------|--------|----------------|---------------|-------|
| [role name]   | [count]     | [count] | [count]| [count]        | [count]       | [X%]  |
```

---

## Output 4 — Separation of Duties Risk Register

List every SoD violation found — these are the highest-risk items for financial integrity:

```
VIOLATION #[N]
  Role:           [role name]
  Module:         [module name]
  Can initiate:   [what they can create/submit]
  Can also close: [what they can approve/pay/complete]
  Financial risk: [monetary exposure if abused]
  Fix:            [exact permissions to move and to which role]
  Priority:       🔴 Critical | 🟠 High
```

---

## Output 5 — Recommended Permission Seeder Changes

For every gap found, produce the exact change needed in the seeder:

```php
// CHANGE #1 — Add missing permissions to [Role Name]
// Reason: [why this is needed]
$role = Role::findByName('[role-name]');
$role->givePermissionTo([
    '[permission.name.1]',
    '[permission.name.2]',
]);

// CHANGE #2 — Remove excess permissions from [Role Name]
// Reason: [why this should be removed]
$role = Role::findByName('[role-name]');
$role->revokePermissionTo([
    '[permission.name.1]',
]);

// CHANGE #3 — New permission needs to be created and assigned
// Reason: [feature exists but has no permission guarding it]
Permission::create(['name' => '[new.permission.name]', 'guard_name' => 'web']);
$role = Role::findByName('[role-name]');
$role->givePermissionTo('[new.permission.name]');
```

---

## Output 6 — Recommended Frontend Fixes

For every frontend mismatch, produce the exact fix:

```
FIX #[N] — [File path]
  Issue:        [description]
  Current code: [what the code looks like now]
  Fixed code:   [what it should look like]
  Permission:   [exact permission string to use]
```

---

## Output 7 — Questions for the Team

For every ambiguity the AI could not resolve from code alone:

```
QUESTION #[N]
  Context:      [what was found in the code that raised this question]
  Question:     [what needs a human decision]
  Why it matters: [what security or UX decision depends on this]
  Options:      [A) ... | B) ... | C) ...]
```

---

*End of role and permission audit prompt.*
*The AI discovers everything from your codebase — it will not invent roles, permissions, or gaps.*
*Paste alongside your full codebase, seeder files, route files, and frontend source.*
