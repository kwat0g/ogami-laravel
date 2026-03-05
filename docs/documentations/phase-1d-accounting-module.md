# Phase 1D — Accounting Module
## Sprints 13–16 · Weeks 25–32

**Goal:** Build a complete double-entry general ledger, accounts payable, accounts receivable, VAT/EWT management, and full PFRS-compliant financial statement suite — all tightly integrated with payroll via automatic GL posting.

---

## Sprint 13 — Chart of Accounts, Journal Entries & GL Auto-Posting
### Weeks 25–26

### What was built

**Chart of Accounts (COA) Hierarchy**

`chart_of_accounts` table: `code`, `name`, `account_type` (Asset, Liability, Equity, Income, Expense), `account_sub_type`, `parent_id` (self-referencing FK for hierarchy), `is_current` (Balance Sheet classification: current vs. non-current), `is_control_account`, `is_active`.

COA-001–006 validation rules enforced by `ChartOfAccountService`:

| Code | Rule |
|---|---|
| COA-001 | Account code must be unique |
| COA-002 | Parent account must be the same account type |
| COA-003 | Control accounts cannot be posted to directly (must use sub-accounts) |
| COA-004 | Inactive accounts cannot receive new postings |
| COA-005 | Account type cannot be changed once entries exist |
| COA-006 | Deleting an account with entries is prohibited — only deactivation allowed |

**Fiscal Period Management**

`fiscal_periods` table: `year`, `month`, `status` (`open`, `closed`, `locked`).

- Open period: transactions can be created and posted
- Closed period: no new transactions; re-opening requires `admin` role + justification
- Locked period: immutable after external audit (admin cannot re-open)

`FiscalPeriodPolicy` enforces these states at the Gate level.

**Journal Entry Workflow**

JE lifecycle: `draft → submitted → posted`

JE-001–010 validation rules:

| Code | Rule |
|---|---|
| JE-001 | At least one debit line and one credit line required |
| JE-002 | Sum of debits must equal sum of credits (double-entry balance) |
| JE-003 | All accounts must be active and postable (no control accounts) |
| JE-004 | Transaction date must fall within an open fiscal period |
| JE-005 | JE reference number is system-generated and immutable after creation |
| JE-006 | Reversal JE must reference the original JE |
| JE-007 | Accounts must belong to the same entity/company (multi-entity guard) |
| JE-008 | A posted JE cannot be edited — only reversed |
| JE-009 | JE description is required (minimum 10 characters) |
| JE-010 (SoD) | The user who submitted the JE cannot be the same user who posts it |

**JE Reference Number**

`JournalEntryService::generateJeNumber(Carbon $date)` produces sequential numbers in format `JE-YYYY-MM-NNNNN` (e.g., `JE-2026-02-00001`). The sequence resets each month. Sequential integrity is enforced by a PostgreSQL sequence, not app-level locks.

**Auto-Posting Service**

`GlAutoPostingService` translates domain events into journal entries automatically. Three triggers:

| Trigger | Debit | Credit |
|---|---|---|
| Payroll run approved | `Salaries Expense` (or dept cost center) | `Accrued Salaries Payable` |
| Payroll disbursed | `Accrued Salaries Payable` | `Cash in Bank` |
| AP invoice posted | `Expense account(s) per line` | `Accounts Payable – Trade` |
| AP payment released | `Accounts Payable – Trade` | `Cash in Bank` |
| AR invoice posted | `Accounts Receivable – Trade` | `Sales Revenue` |
| AR payment received | `Cash in Bank` | `Accounts Receivable – Trade` |

The auto-posting service reads the GL account mappings from `system_settings` (`gl_account_payroll_expense`, `gl_account_cash_in_bank`, etc.) — fully configurable by the admin.

**PostgreSQL Immutability Trigger**

A `BEFORE UPDATE OR DELETE` trigger on `journal_entry_lines` raises an exception if the parent JE is in `posted` status. This enforces immutability at the database layer — even if application code has a bug, posted entries cannot be mutated.

---

## Sprint 14 — Accounts Payable & Vendor Management
### Weeks 27–28

### What was built

**Vendor Master**

`vendors` table: `name`, `tin` (TIN encrypted same method as employee govt IDs), `atc_code` (expanded withholding tax ATC from `ewt_rate_configs`), `payment_terms_days`, `default_expense_account`, `is_active`.

**AP Invoice Workflow**

```
draft → submitted → approved → posted → paid
              ↓           ↓
           rejected    rejected
```

AP-001–011 rules enforced by `ApInvoiceService`:

| Code | Rule |
|---|---|
| AP-001 | Vendor must be active |
| AP-002 | Invoice date must be within an open fiscal period |
| AP-003 | Invoice number must be unique per vendor |
| AP-004 | At least one line item required |
| AP-005 | Line total must match header total |
| AP-006 | Expense account must be active and not a control account |
| AP-007 | EWT must be computed if vendor ATC code is present |
| AP-008 | EWT rate must match the current period's `ewt_rate_configs` for the ATC |
| AP-009 | Due date is calculated from invoice date + vendor payment terms |
| AP-010 | Posting an AP invoice auto-posts a JE via `GlAutoPostingService` |
| AP-011 (SoD) | The user who submitted the invoice cannot approve it |

**AP Due Date Monitor**

Dashboard widget with three columns:
- **Current** — invoices due in the next 30 days
- **Overdue** — past due date, unpaid
- **Critical** — overdue by more than 30 days

Color-coded table; Managers and Executives see this on their landing page.

**Aging Report Engine**

`ApAgingReportService::generate(Carbon $asOf)` produces the classic AP aging buckets:
- Current (0–30 days)
- 31–60 days
- 61–90 days
- 91+ days

Exportable as Excel via `GET /api/accounting/ap/aging/export`.

**Daily Email Digest**

`SendApDailyDigestJob` runs at 8:00 AM via `schedule:run`. Sends a summary of:
- New invoices awaiting approval
- Invoices due today
- Overdue invoices by count and total amount

Recipients: Accounting department manager's email from `system_settings`.

**BIR Form 2307 (EWT Certificate)**

`BirForm2307Service::generate(int $vendorId, int $year, int $quarter)` — one PDF per vendor per quarter. Content: vendor name/TIN, ATC, gross payments per month, EWT withheld per month, quarterly total. Generated on-demand via `GET /api/accounting/ap/form-2307/{vendorId}`.

---

## Sprint 15 — Accounts Receivable & VAT Management
### Weeks 29–30

### What was built

**Customer Master**

`customers` table: `name`, `tin`, `credit_limit`, `payment_terms_days`, `default_income_account`, `is_vat_registered`, `is_active`.

AR-001–006 rules enforced by `ArInvoiceService`:

| Code | Rule |
|---|---|
| AR-001 | Customer must be active |
| AR-002 | Invoice date must fall within an open fiscal period |
| AR-003 | Invoice number unique per customer |
| AR-004 | At least one line item; line totals must match header |
| AR-005 | Credit limit check: new invoice cannot push customer's outstanding balance beyond limit (warning, not hard block) |
| AR-006 | Posting an AR invoice auto-posts a JE via `GlAutoPostingService` |

**Customer Invoice Workflow**

```
draft → submitted → approved → posted → partially_paid → paid
              ↓           ↓
           rejected    rejected
```

Partial payments are tracked in `ar_payments`; the invoice balance (`amount - sum(payments)`) is updated on each payment application.

**VAT Ledger**

`VatLedgerService` maintains a running VAT ledger per fiscal period:
- **Input VAT** — VAT on purchases (from AP invoices where vendor is VAT-registered)
- **Output VAT** — VAT on sales (from AR invoices)
- **Net VAT payable** = Output VAT − Input VAT
- **Carry-forward credit** — when Input > Output, excess is carried to next period

BIR VAT Relief format export (Excel) generated by `VatReliefExportService`.

**EWT Rate Table**

`ewt_rate_configs` table: `atc_code`, `description`, `rate`, `is_active`. Configurable by admin (rates change per BIR revenue regulation). The AP module and payroll BIR reports read from this table — single source of truth.

**Tax Period Reports**

Accessible from the Accounting module's Reports section:
- Monthly VAT summary (per period, input/output/net)
- EWT summary per ATC code per month
- VAT carry-forward ledger

---

## Sprint 16 — Financial Statements
### Weeks 31–32

### What was built

**General Ledger Report**

`GlReportService::generate(array $filters)` — filter by account, date range, cost center (department). Returns all journal entry lines with running balance. Exportable as Excel.

**Trial Balance**

`TrialBalanceService::generate(Carbon $asOf)` — aggregates GL postings per account code up to `$asOf` date. Returns `debit_balance`, `credit_balance`, `net_balance` per account. Validates that sum of all debits = sum of all credits (proof of double-entry integrity). Exportable as Excel.

**Balance Sheet (PFRS — IAS 1 format)**

`BalanceSheetService::generate(Carbon $asOf)` — classified balance sheet:

```
Assets
  Current Assets (is_current = true, type = Asset)
  Non-Current Assets (is_current = false, type = Asset)
Liabilities
  Current Liabilities
  Non-Current Liabilities
Equity
  Share Capital, Retained Earnings, Current Period Profit
```

Total Assets must equal Total Liabilities + Equity or a warning is shown. Comparative period toggle computes the prior year balance as well.

**Income Statement (PFRS — IAS 1 format)**

`IncomeStatementService::generate(Carbon $from, Carbon $to)`:
- Revenue accounts (type = Income)
- Cost of Sales accounts
- Gross Profit
- Operating Expenses by sub-type
- Operating Income
- Other Income / Other Expenses
- Income Before Tax
- Provision for Income Tax
- **Net Income**

**Cash Flow Statement (Indirect Method)**

`CashFlowService::generate(Carbon $from, Carbon $to)`:
1. **Operating Activities**: Net income, add non-cash items (depreciation), adjust working capital changes (Δ AR, Δ AP, Δ inventory)
2. **Investing Activities**: Capital expenditure (from fixed asset accounts)
3. **Financing Activities**: Loan proceeds/repayments, dividends
4. **Net Increase in Cash** → reconciles to `Cash in Bank` ending balance

**Bank Reconciliation**

`BankReconciliationService`:
- Upload CSV bank statement → auto-match against GL `Cash in Bank` entries by amount + date ±3 days
- Unmatched GL entries: outstanding checks / deposits in transit
- Unmatched bank entries: bank charges, interest not yet booked

---

## Phase 1D Summary

| Item | Delivered |
|---|---|
| COA hierarchy CRUD + COA-001–006 | ✅ |
| Fiscal period management (open/close/lock) | ✅ |
| JE workflow (draft → submitted → posted) | ✅ |
| JE-001–010 validation + SoD-010 | ✅ |
| GL auto-posting service (payroll + AP + AR) | ✅ |
| PostgreSQL immutability trigger | ✅ |
| Vendor master + TIN + ATC | ✅ |
| AP invoice workflow + AP-001–011 | ✅ |
| AP due date monitor (3 columns) | ✅ |
| AP aging report | ✅ |
| Daily 8 AM email digest | ✅ |
| BIR Form 2307 (EWT) | ✅ |
| Customer master + AR-001–006 | ✅ |
| AR invoice + partial payment | ✅ |
| VAT ledger (input/output/carry-forward) | ✅ |
| EWT rate table | ✅ |
| General Ledger report | ✅ |
| Trial Balance | ✅ |
| Balance Sheet (PFRS classified) | ✅ |
| Income Statement (PFRS) | ✅ |
| Cash Flow Statement (indirect) | ✅ |
| Bank reconciliation | ✅ |

---

*Previous: [Phase 1C — Payroll Engine](phase-1c-payroll-engine.md) · Next: [Phase 1E — QA, Security & Launch](phase-1e-qa-security-launch.md)*
