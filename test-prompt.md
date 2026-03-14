# 🧪 OGAMI ERP — COMPLETE SYSTEM TESTING GUIDE GENERATION PROMPT
# For: GitHub Copilot Agent Mode / Any AI with cocoindex + context7 active
# Purpose: Generate an accurate, step-by-step manual testing guide for every
#          module and feature in the ERP — grounded in actual frontend code,
#          real button labels, real field names, real validation messages,
#          and real role/permission structure
# Paste into Agent chat before starting

---

You are a **senior QA engineer and ERP implementation specialist**.
Your job is to generate a complete, accurate, step-by-step manual testing guide
for this entire ERP system.

## Why This Prompt Exists

AI-generated test guides are often useless because they hallucinate:
- Button labels that don't exist in the actual UI
- Field names that don't match what the frontend renders
- Workflows that work differently than described
- Wrong navigation paths
- Missing steps that are required by the actual business logic

This prompt prevents all of that by requiring you to **read the actual code
before writing a single test step**.

## Your Contract With The Tester

Every step you write must be:
- **Accurate** — the button, field, or path exists exactly as you describe it
- **Sequenced** — steps happen in the order the actual UI requires them
- **Role-correct** — the logged-in user for each step has the permission to do it
- **Verifiable** — every step has a clear expected result the tester can confirm
- **Cross-module aware** — when a step in module A affects module B, that effect
  is a separate verification step

---

---

# PHASE 0 — SYSTEM ANALYSIS (do all of this BEFORE writing a single test step)

---

You must complete this entire phase before writing any test guide.
If you skip this phase, your test steps will be inaccurate.

## Step 0.1 — Discover every module

```
cocoindex_code_search: "app/Domains subdirectory module service"
cocoindex_code_search: "routes/api route file module group"
cocoindex_code_search: "frontend pages directory module component"
cocoindex_code_search: "sidebar navigation menu module section"
```

List every module discovered. This is your testing scope.

---

## Step 0.2 — Discover every role and permission

```
cocoindex_code_search: "Role::create seeder permission assign"
cocoindex_code_search: "givePermissionTo role permission list"
cocoindex_code_search: "Permission::create name guard seeder"
```

For every role discovered:
- What is the exact role name?
- What modules can they access?
- What actions can they perform?
- What workflow steps do they participate in?
- What approval levels do they hold?

---

## Step 0.3 — Discover every frontend page and its exact UI elements

This is the most important step. For every module, read the actual
frontend page components and record exactly what exists:

```
cocoindex_code_search: "[module-name] page component buttons fields"
cocoindex_code_search: "[module-name] form input label placeholder"
cocoindex_code_search: "[module-name] table column header action"
cocoindex_code_search: "[module-name] modal dialog confirm message"
cocoindex_code_search: "[module-name] validation error message text"
cocoindex_code_search: "[module-name] status badge label text"
cocoindex_code_search: "[module-name] tab label section heading"
cocoindex_code_search: "[module-name] dropdown select option value"
cocoindex_code_search: "[module-name] toast notification success error"
```

For every page, record:
- Exact navigation path (sidebar label → submenu label → page title)
- Every button's exact label text as rendered
- Every form field's exact label text as rendered
- Every table column header exactly as rendered
- Every status value exactly as stored and displayed
- Every validation message exactly as shown
- Every success/error toast message exactly as shown
- Every modal title and confirm button text exactly as shown

**You will use these exact strings in every test step you write.**
**Never invent a button label. Never invent a field name.**
**If you cannot find the exact text in the code, flag it as UNVERIFIED.**

---

## Step 0.4 — Discover every workflow and its states

```
cocoindex_code_search: "status workflow state transition pending approved"
cocoindex_code_search: "workflow steps approval chain hierarchy"
cocoindex_code_search: "status badge color label display"
```

For every workflow:
- What are the exact status values?
- What triggers each transition?
- Who can trigger each transition (which permission)?
- What happens in other modules when each transition occurs?

---

## Step 0.5 — Discover all cross-module effects

```
cocoindex_code_search: "dispatch event job notify after create"
cocoindex_code_search: "update affect impact linked related module"
cocoindex_code_search: "trigger create auto generate from"
```

Map exactly which actions in one module create, update, or trigger
something in another module. These become verification steps in the test guide.

---

## Step 0.6 — Discover all validation rules

```
cocoindex_code_search: "FormRequest rules required validation"
cocoindex_code_search: "frontend validation schema yup zod rules"
cocoindex_code_search: "error message validation fail"
```

For every form, record every validation rule so test steps can
verify that invalid input is properly rejected.

---

---

# PHASE 1 — TEST USER ACCOUNT SETUP

---

Before any testing begins, the tester needs properly configured user accounts
that reflect the real role hierarchy of this ERP.

## Step 1.1 — Discover the employee and user relationship

```
cocoindex_code_search: "User employee relationship linked belongs"
cocoindex_code_search: "employee user account create assign"
cocoindex_code_search: "user employee_id foreign key"
```

Understand exactly how a user account is linked to an employee record
in this system — find the exact fields and relationships.

---

## Step 1.2 — Discover all roles that need test accounts

From Step 0.2, identify every role that:
- Has unique permissions not shared with other roles
- Participates in a distinct workflow step
- Appears at a specific approval level

Every such role needs its own test user account.

---

## Step 1.3 — Generate the complete test account setup guide

Produce a step-by-step guide to create all test accounts BEFORE testing begins.

For every test account needed, the guide must specify:

```
TEST ACCOUNT: [role name]
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PURPOSE: [what this account is used for in testing]

STEP 1 — Create the employee record first
  Navigate to: [exact sidebar path discovered from frontend]
  Click: [exact button label discovered from frontend]
  Fill in these fields:
    [exact field label]: [test value to use]
    [exact field label]: [test value to use]
    [exact field label]: [test value to use]
    Department:          [department this role belongs to]
    Position:            [position that matches this role]
    [any other required fields discovered from the form]
  Click: [exact save/submit button label]
  Verify: [exact success message or redirect that should occur]

STEP 2 — Create the user account linked to this employee
  Navigate to: [exact path to user management]
  Click: [exact button label]
  Fill in:
    [exact field label]: [test email to use — e.g. test.[role]@ogami.test]
    [exact field label]: [test password — e.g. Test1234!]
    Employee link:       [link to the employee created in Step 1]
    Role:                [exact role name to assign]
  Click: [exact save button label]
  Verify: [exact confirmation message]

CREDENTIALS TO NOTE DOWN:
  Email:    test.[role-slug]@ogami.test
  Password: Test1234!
  Role:     [exact role name]
  Employee: [employee name used]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

**Required accounts to always include (never remove):**
- `admin` — keep existing account, do not recreate
- `superadmin` — keep existing account, do not recreate

**Discover and create accounts for every other role found.**
Do not assume which roles exist — use what you found in Step 0.2.

---

## Step 1.4 — Test account reference card

After the setup guide, produce a quick reference card the tester keeps open:

```
╔════════════════════════════════════════════════════════╗
║  TEST ACCOUNT QUICK REFERENCE                          ║
╠══════════════════╦═════════════════════╦═══════════════╣
║  Role            ║  Email              ║  Used For     ║
╠══════════════════╬═════════════════════╬═══════════════╣
║  [role]          ║  test.[role]@...    ║  [modules]    ║
║  [role]          ║  test.[role]@...    ║  [modules]    ║
╚══════════════════╩═════════════════════╩═══════════════╝
```

---

---

# PHASE 2 — TEST GUIDE STRUCTURE RULES

---

Before writing any test, follow these rules for every single step:

## Rule 1 — Always specify the logged-in user

Every section header must state which account to be logged in as:
```
👤 LOGGED IN AS: test.[role]@ogami.test ([Role Name])
```

If a test section requires switching accounts mid-way (e.g. for approvals),
mark the switch explicitly:
```
🔄 SWITCH ACCOUNT: Log out → Log in as test.[approver-role]@ogami.test
```

## Rule 2 — Always use exact discovered UI text

- Navigation: use exact sidebar label text discovered from frontend code
- Buttons: use exact button label text with quotation marks e.g. click **"Save Changes"**
- Fields: use exact field label text e.g. fill in **"Employee No."** field
- Status: use exact status text e.g. status changes to **"Pending Approval"**
- Messages: use exact toast/alert text e.g. should see **"Record saved successfully"**

## Rule 3 — Always include expected results

Every action step must be followed by a verification step:
```
Action:   Click the "[exact button]" button
Verify:   [exact outcome — page redirect, toast message, status change,
          record appears in table, field becomes disabled, etc.]
```

## Rule 4 — Always include negative test cases

For every form or action, include at least one test where invalid
input is submitted and the correct error is shown:
```
Negative test: Leave [required field] empty → click "[submit button]"
Verify: Error message "[exact validation message]" appears under the field
```

## Rule 5 — Always include cross-module verification

When an action in one module is supposed to affect another module,
add an explicit cross-module check step:
```
Cross-module check: Navigate to [other module page]
Verify: [exact record/field/status that should have changed]
```

## Rule 6 — Always include permission boundary tests

For every restricted action, include a test that confirms an
unauthorized role cannot perform it:
```
Permission test:   Log in as [role without permission]
Navigate to:       [page]
Verify:            [button is hidden / page is inaccessible /
                   API returns 403 / redirect occurs]
```

## Rule 7 — Always include data prerequisites

Before each module test, state what data must already exist:
```
Prerequisites:
  ✅ [data that must exist before this test] — [where to create it if not]
  ✅ [another prerequisite]
```

---

---

# PHASE 3 — MODULE TEST GUIDES

---

For every module discovered in Phase 0, generate a complete test guide
following the structure below. Discover the module details first,
then write the guide.

---

## Module Test Guide Template

For each module, before writing the guide, run:

```
cocoindex_code_search: "[module-name] page list create edit delete"
cocoindex_code_search: "[module-name] form fields validation required"
cocoindex_code_search: "[module-name] status workflow approve reject"
cocoindex_code_search: "[module-name] permission guard frontend backend"
cocoindex_code_search: "[module-name] cross module integration affect"
cocoindex_code_search: "[module-name] report export print"
```

Then write the guide:

---

```
═══════════════════════════════════════════════════════════
MODULE: [Module Name — discovered]
═══════════════════════════════════════════════════════════

OVERVIEW:
  [What this module does in business terms — discovered from code]

ACCOUNTS NEEDED:
  [List every test account needed for this module's tests]

PREREQUISITES:
  [List all data that must exist before testing this module]

───────────────────────────────────────────────────────────
TEST SECTION [N]: [Feature Name]
👤 LOGGED IN AS: [exact test account]
───────────────────────────────────────────────────────────

HAPPY PATH TEST — [what this tests]

  Prerequisites:
    ✅ [prerequisite 1]
    ✅ [prerequisite 2]

  Steps:
    1. Navigate to: [exact sidebar → submenu → page path]
       Verify: Page title shows "[exact page title]"
               Table shows [describe what should be visible]

    2. Click the "[exact button label]" button
       Verify: [exact modal/page/panel that should open]
               Form shows these fields: [list exact field labels]

    3. Fill in "[exact field label]" with: [test value]
       Fill in "[exact field label]" with: [test value]
       Fill in "[exact field label]" with: [test value]
       [list every required field with exact label and test value]

    4. Click the "[exact submit button label]" button
       Verify: Success message "[exact toast text]" appears
               Record appears in the table with status "[exact status]"
               Record shows: [key fields that should be visible in table]

  NEGATIVE TEST — Required field validation
    5. Click "[exact button label]" to open the form again
       Leave "[exact required field]" empty
       Click "[exact submit button label]"
       Verify: Error "[exact validation message]" appears under the field
               Form does not close

  NEGATIVE TEST — Permission boundary
    6. 🔄 SWITCH ACCOUNT: Log out → Log in as [role without this permission]
       Navigate to: [same page]
       Verify: "[exact button label]" button is NOT visible
               [or: page redirects to [path], or: shows "[exact error message]"]
       🔄 SWITCH ACCOUNT: Log back in as [original test account]

───────────────────────────────────────────────────────────
TEST SECTION [N+1]: [Workflow/Approval Feature]
👤 LOGGED IN AS: [initiator role account]
───────────────────────────────────────────────────────────

  [Follow the workflow from creation through every approval level,
   switching accounts at each step that requires a different role.
   At every transition, verify the exact status change and any
   cross-module effects.]

  Steps:
    1. [initiator creates record]
       ...

    2. [initiator submits for approval]
       Verify: Status changes to "[exact pending status text]"
               [approver role account] receives notification:
               "[exact notification text — if discovered]"

    3. 🔄 SWITCH ACCOUNT: Log in as [approver role account]
       Navigate to: [exact path to approval queue or record]
       Verify: Record appears with status "[exact status]"
               "[exact approve button label]" button is visible

    4. Click "[exact approve button label]"
       [fill any approval fields discovered — e.g. notes, date]
       Click "[exact confirm button label]"
       Verify: Status changes to "[exact approved status]"
               [list any cross-module effects that should occur]

  CROSS-MODULE VERIFICATION:
    5. Navigate to: [affected module page]
       Verify: [exact record/field/value that should have changed]

  SELF-APPROVAL BOUNDARY TEST:
    6. 🔄 SWITCH ACCOUNT: Log in as [initiator account]
       Navigate to the record just created
       Verify: "[exact approve button]" is NOT visible to the initiator
               [or: attempt to approve own record shows "[exact error]"]

───────────────────────────────────────────────────────────
TEST SECTION [N+2]: [Report / Export Feature]
👤 LOGGED IN AS: [role with report access]
───────────────────────────────────────────────────────────

  Steps:
    1. Navigate to: [exact path to report page]
       Verify: Report page shows with these filter options: [list exact filters]

    2. Set filter "[exact filter label]" to: [test value]
       Click "[exact generate/search button label]"
       Verify: Report renders with data matching the filter
               Shows columns: [list exact column headers discovered]

    3. Click "[exact export button label]"
       Verify: File downloads as [exact file type — xlsx/pdf]
               File name follows pattern: [pattern if discoverable]

  PERMISSION TEST:
    4. 🔄 SWITCH ACCOUNT: Log in as [role without report access]
       Navigate to: [same report page]
       Verify: Page is inaccessible or "[exact export button]" is hidden

═══════════════════════════════════════════════════════════
```

---

Repeat this full structure for every module discovered in Phase 0.

---

---

# PHASE 4 — CROSS-MODULE INTEGRATION TESTS

---

These tests verify that the seams between modules work correctly.
They are separate from individual module tests because they require
multiple accounts and track data across module boundaries.

## Step 4.1 — Discover all cross-module flows

```
cocoindex_code_search: "end to end flow workflow cross module"
cocoindex_code_search: "from module A to module B create generate"
cocoindex_code_search: "hire to retire procure to pay produce to ship"
```

For every end-to-end flow discovered, produce a full integration test:

```
═══════════════════════════════════════════════════════════
INTEGRATION TEST: [Flow Name — e.g. discovered hire-to-retire flow]
═══════════════════════════════════════════════════════════

ACCOUNTS NEEDED:
  [every account needed across the full flow]

DATA SETUP:
  [everything that must exist before this test]

FLOW STEPS:
  [Each step in the actual sequence, with account switches,
   exact UI elements, and cross-module verification at each handoff]

DATA INTEGRITY CHECKS:
  After completing the full flow, verify:
  ✅ [module A] shows: [exact expected state]
  ✅ [module B] shows: [exact expected state]
  ✅ [module C] shows: [exact expected state]

═══════════════════════════════════════════════════════════
```

---

---

# PHASE 5 — SYSTEM-WIDE BOUNDARY TESTS

---

These tests are not module-specific — they verify system-level behavior.

## Step 5.1 — Authentication and session tests

```
cocoindex_code_search: "login logout session token expire auth"
cocoindex_code_search: "wrong password invalid credentials error"
```

Generate tests for:
- Login with correct credentials → verify landing page for each role
- Login with wrong password → verify exact error message shown
- Session expiry behavior → verify what happens when token expires
- Logout → verify session is cleared and redirect occurs

---

## Step 5.2 — Permission boundary system tests

For every sensitive page and action discovered, generate a test that
verifies unauthorized roles are properly blocked — both at the
navigation level and at the direct URL level:

```
cocoindex_code_search: "route guard permission redirect unauthorized"
cocoindex_code_search: "403 unauthorized forbidden response"
```

---

## Step 5.3 — Data validation system tests

```
cocoindex_code_search: "max length required unique format validation"
cocoindex_code_search: "money centavos integer amount validation"
```

For every critical validation rule discovered, generate a test that
submits invalid data and verifies the exact error message.

---

## Step 5.4 — Concurrent workflow tests

Where workflows involve multiple roles acting on the same record,
test edge cases:
- What happens if two approvers try to approve at the same time?
- What happens if a record is edited while pending approval?
- What happens if an approver tries to approve an already-rejected record?

---

---

# PHASE 6 — TEST EXECUTION ORDER AND DEPENDENCIES

---

After generating all module tests, produce a master execution order
that accounts for data dependencies between modules:

```
MASTER TEST EXECUTION ORDER
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

STAGE 0 — Test environment setup
  □ Create all test employee records
  □ Create all test user accounts
  □ Verify all accounts can log in

STAGE 1 — Foundation modules (no dependencies on other modules)
  □ [Module A] — [reason it has no dependencies]
  □ [Module B]

STAGE 2 — Dependent modules (depend on Stage 1 data)
  □ [Module C] — requires [data from Module A]
  □ [Module D] — requires [data from Module B]

STAGE 3 — Workflow modules (require Stage 1 + 2 data)
  □ [Module E] — [dependencies]

STAGE 4 — Cross-module integration tests
  □ [Integration flow A]
  □ [Integration flow B]

STAGE 5 — Reporting and export tests
  □ [All report tests — require data from all prior stages]

STAGE 6 — Boundary and permission tests
  □ [All permission boundary tests]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

---

# PHASE 7 — TEST EVIDENCE COLLECTION GUIDE

---

For every test, specify what evidence the tester should capture:

```
EVIDENCE TO CAPTURE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

For each test section:
  📸 Screenshot: [what to capture — e.g. record created with status shown]
  📸 Screenshot: [approval screen before clicking approve]
  📸 Screenshot: [success message visible]
  📸 Screenshot: [cross-module effect visible]
  📁 File:       [downloaded export file — rename to test-[module]-[date]]

For failed tests:
  📸 Screenshot: [error state]
  📋 Copy:       [exact error message text]
  📋 Note:       [exact steps that led to the failure]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

---

# PHASE 8 — KNOWN GAPS FLAG SYSTEM

---

As you generate the test guide, you will encounter features that are
incomplete, broken, or missing. Do not skip them — flag them:

```
⚠️ GAP FOUND DURING TEST GUIDE GENERATION
  Module:   [module name]
  Feature:  [what was expected]
  Evidence: [what you found in the code that shows it's incomplete]
  Impact:   [what the tester cannot test because of this gap]
  Workaround: [if any — or "cannot test"]
```

Collect all gaps into a summary at the end:

```
UNTESTABLE FEATURES (gaps found during analysis)
  1. [feature] — [reason] — [evidence from code]
  2. [feature] — [reason] — [evidence from code]
```

---

---

# FINAL DELIVERABLE FORMAT

---

The complete output of this prompt is a single document organized as:

```
1. INTRODUCTION
   - System overview (discovered)
   - How to use this guide
   - Test environment requirements

2. TEST ACCOUNT SETUP GUIDE
   - Step-by-step account creation for every role
   - Quick reference card

3. TEST EXECUTION ORDER
   - Master dependency map
   - Stage-by-stage execution plan

4. MODULE TEST GUIDES (one section per module discovered)
   - [Module A]
   - [Module B]
   - [Module N]

5. CROSS-MODULE INTEGRATION TESTS
   - [Flow A]
   - [Flow B]

6. SYSTEM-WIDE BOUNDARY TESTS
   - Auth tests
   - Permission tests
   - Validation tests

7. EVIDENCE COLLECTION GUIDE

8. KNOWN GAPS AND UNTESTABLE FEATURES
```

---

## The Standard You Are Held To

Before finalizing any test step, ask yourself:

- Did I search the actual frontend code for this button/field/message?
- Is the navigation path I wrote exactly what the sidebar renders?
- Does this role actually have permission to do this step?
- Did I account for what happens in other modules after this action?
- Does the tester know exactly what to look for to confirm success?

If the answer to any of these is "I assumed" — go back and search the code.

---

*This prompt uses cocoindex to read your actual frontend components,*
*service classes, seeders, and route files before writing a single test step.*
*The result is a test guide that matches what actually exists in the system —*
*not what a generic ERP is supposed to look like.*