---
name: budget-planner
description: "Budget module expertise for Ogami ERP. Use when creating cost centers, setting annual budget lines, running utilisation analysis, debugging approval workflow issues, or integrating budget checks into Procurement/AP. Covers the Budget domain service, SoD constraints, and GL-linked utilisation queries."
argument-hint: "Describe the task (e.g. 'set up Q1 2026 budget for the Production department' or 'why is budget utilisation showing zero actuals?')"
---
# Budget Planner Skill

Domain knowledge for the Budget module (`app/Domains/Budget/`), including cost center hierarchy, annual budget lines, approval workflow, and utilisation analysis.

## When to Use

- Creating or updating cost centers (flat or hierarchical)
- Setting annual budget lines for a cost center + account + fiscal year
- Moving a budget line through the approval workflow (submit → approve / reject)
- Reading or debugging utilisation figures (budgeted vs. actual spend)
- Integrating `hasAvailableBudget()` into Procurement or AP spend guards
- Writing or debugging `BudgetController` actions or `BudgetService` methods
- Writing tests for Budget feature endpoints

---

## Domain Architecture

```
CostCenter           — organisational budget unit; self-referential hierarchy
  └── AnnualBudget   — one line per (cost_center, fiscal_year, account)
```

**Key service**: `app/Domains/Budget/Services/BudgetService.php`  
**Controller**: `app/Http/Controllers/Budget/BudgetController.php`  
**Routes**: `routes/api/v1/budget.php` (prefix `/api/v1/budget`)  
**Frontend hook**: `frontend/src/hooks/useBudget.ts`  
**Types**: `frontend/src/types/budget.ts`

---

## Cost Centers

### Rules
- `code` is **always stored uppercase** — the service calls `strtoupper()`. Never rely on the client to uppercase it.
- `parent_id` creates a hierarchy (e.g., `Plant → Line → Cell`); self-referential FK on `cost_centers.id`.
- `department_id` is optional; sets the owning department without limiting access.
- Soft-deleted cost centers are excluded from budget lines by cascading delete guard (`restrictOnDelete` FK).

### Service Methods
```php
$service->storeCostCenter(array $data, User $actor): CostCenter
// $data: name*, code*, description?, department_id?, parent_id?, is_active?

$service->updateCostCenter(CostCenter $cc, array $data, User $actor): CostCenter
// All fields optional (sometimes). Handles explicit null for department_id and parent_id.
```

---

## Budget Lines (AnnualBudget)

### Unique Constraint
`(cost_center_id, fiscal_year, account_id)` — only **one budget line per account per year per cost center**. `setBudgetLine()` performs an **upsert** (firstOrNew), so calling it a second time updates the amount rather than creating a duplicate.

### Status Lifecycle
```
draft ──> submitted ──> approved
  ^           │
  └───────────┘ (rejected → can resubmit)
```

### SoD Constraint (enforced at DB level)
`approved_by_id <> submitted_by_id` — a database CHECK constraint prevents the same user from both submitting and approving. The service additionally throws:
```php
throw new DomainException('SOD_VIOLATION', 'SOD_VIOLATION', 403);
```

### Service Methods
```php
$service->setBudgetLine(array $data, User $actor): AnnualBudget
// $data: cost_center_id*, fiscal_year*, account_id*, budgeted_amount_centavos*, notes?
// UPSERT: updates existing line if (cc, year, account) tuple already exists

$service->submitBudget(AnnualBudget $budget, User $actor): AnnualBudget
// Only 'draft' or 'rejected' → 'submitted'. Throws BUDGET_INVALID_STATUS (422) otherwise.
// Clears all approval fields on resubmission.

$service->approveBudget(AnnualBudget $budget, User $actor, ?string $remarks): AnnualBudget
// Only 'submitted' → 'approved'. Throws SOD_VIOLATION (403) if actor === submitter.

$service->rejectBudget(AnnualBudget $budget, User $actor, ?string $remarks): AnnualBudget
// Only 'submitted' → 'rejected'.
```

---

## Utilisation Analysis

`getUtilisation(CostCenter $cc, int $fiscalYear): array` returns:

```php
[
  'cost_center' => [...],
  'fiscal_year' => 2026,
  'lines' => [
    [
      'budget_ulid'              => '...',
      'account_id'               => 42,
      'account_code'             => '5-1001',
      'account_name'             => 'Raw Materials',
      'normal_balance'           => 'DEBIT',
      'budgeted_amount_centavos' => 5_000_000,
      'actual_amount_centavos'   => 3_200_000,
      'variance_centavos'        => 1_800_000,  // positive = under budget
      'utilisation_pct'          => 64.0,       // >100 = over budget
    ],
    // ...
  ],
]
```

**How actuals are computed:**
- Joins `journal_entry_lines` for entries where `posted_at` year matches.
- Uses `account.normal_balance` (DEBIT/CREDIT) to determine sign.
- Converts JEL decimals to centavos (`× 100`) for comparison.

**Debugging zero actuals:**
1. Check that JEs are in `posted` status and `posted_at` is in the correct year.
2. Check that `journal_entry_lines.cost_center_id` is set — only lines with a cost center are included.
3. Verify that `journal_entry_lines.cost_center_id` is now a `bigint` FK (migration `000011` widened it).

---

## Spend Guard Integration

`hasAvailableBudget()` is called before approving purchase requests:

```php
$service->hasAvailableBudget(
    costCenterId: $pr->cost_center_id,
    accountId:    $pr->account_id,
    fiscalYear:   now()->year,
    requestedCentavos: $pr->total_amount_centavos
): bool
```

- Returns `true` if no budget line exists (no ceiling enforced).
- Returns `false` if `(current_spend + requested) > budgeted`.
- Only counts spend from **posted** JEs.

---

## Permissions

| Permission | Who holds it |
|-----------|-------------|
| `budget.view` | All roles with budget access |
| `budget.manage` | `admin`, `executive`, `vice_president`, `manager` |
| `budget.approve` | `admin`, `executive`, `vice_president` |

The `approve` and `reject` routes use `middleware('permission:budget.approve')` directly — there is no `approve()` method in `BudgetPolicy`.

---

## Route Reference

| Method | URI | Action | Middleware |
|--------|-----|--------|-----------|
| GET | `/api/v1/budget/cost-centers` | `indexCostCenters` | `budget.view` |
| POST | `/api/v1/budget/cost-centers` | `storeCostCenter` | `budget.manage` |
| PATCH | `/api/v1/budget/cost-centers/{costCenter}` | `updateCostCenter` | `budget.manage` |
| GET | `/api/v1/budget/lines` | `indexBudgets` | `budget.view` — requires `cost_center_id` + `fiscal_year` |
| POST | `/api/v1/budget/lines` | `setBudgetLine` | `budget.manage` |
| GET | `/api/v1/budget/utilisation/{costCenter}` | `utilisation` | `budget.view` — requires `fiscal_year` query param |
| PATCH | `/api/v1/budget/lines/{annualBudget}/submit` | `submitBudget` | `budget.manage` |
| PATCH | `/api/v1/budget/lines/{annualBudget}/approve` | `approveBudget` | `budget.approve` |
| PATCH | `/api/v1/budget/lines/{annualBudget}/reject` | `rejectBudget` | `budget.approve` |

Route parameters `{costCenter}` and `{annualBudget}` resolve via ULID (both models use `HasPublicUlid`).

---

## Type Divergence to Know

`frontend/src/types/budget.ts` → `AnnualBudget` does **not** include `status`, `submitted_by_id`, `approved_by_id`, or approval timestamps. Those fields are only in the inline type inside `useBudget.ts`. When extending frontend types, update both locations.

---

## Test Setup Pattern

```php
beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'ChartOfAccountsSeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'FiscalPeriodSeeder'])->assertExitCode(0);

    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');

    $this->executive = User::factory()->create();
    $this->executive->assignRole('executive');
});
```

Key notes for Budget tests:
- `setBudgetLine` is an upsert — calling it twice with the same `(cc, year, account)` updates, not duplicates.
- SoD: use **two different users** for submit and approve, or the DB CHECK constraint will reject.
- Utilisation actuals require **posted** JEs with `cost_center_id` set on the lines.
