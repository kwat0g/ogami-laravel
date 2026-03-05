# Manager Workflow Guide — Accounting Department

**System:** Ogami Manufacturing Philippines Corp. ERP  
**Role:** `manager` (assigned to Accounting Department via RDAC)  
**Last Updated:** 2026-02  

---

## Table of Contents

1. [Chart of Accounts](#1-chart-of-accounts)
2. [Journal Entry Workflow](#2-journal-entry-workflow)
3. [Accounts Payable (AP) Workflow](#3-accounts-payable-ap-workflow)
4. [Accounts Receivable (AR) Workflow](#4-accounts-receivable-ar-workflow)
5. [Bank Reconciliation](#5-bank-reconciliation)
6. [Monthly Period Close Procedure](#6-monthly-period-close-procedure)
7. [Financial Reports](#7-financial-reports)
8. [VAT Compliance](#8-vat-compliance)

---

## 1. Chart of Accounts

### 1.1 Account Structure

Accounts follow a 7-digit code:

```
1 xxx xxx   Assets
2 xxx xxx   Liabilities
3 xxx xxx   Equity
4 xxx xxx   Revenue
5 xxx xxx   Cost of Goods Sold
6 xxx xxx   Operating Expenses
7 xxx xxx   Other Income / Expense
```

### 1.2 Adding an Account

1. **Accounting › Chart of Accounts › New Account**.
2. Fill in: code, name, account type, normal balance (Dr / Cr), and parent account.
3. Check **Allow Posting** only for leaf accounts (transactions cannot post to control accounts).
4. Click **Save** — the account is immediately available for journal entries.

### 1.3 Inactivating an Account

Accounts with a non-zero balance cannot be inactivated.

1. Zero out the balance via a transfer entry.
2. Open the account → **⋮ Actions → Inactivate**.

---

## 2. Journal Entry Workflow

### 2.1 Life-Cycle States

```
draft → submitted → posted
          ↓
       rejected → (edit) → submitted
```

- **Draft** — editable, not yet reviewed.
- **Submitted** — pending accounting manager review; locked for editing.
- **Posted** — committed to the ledger; only a reversing entry can undo it.
- **Rejected** — returned to preparer with notes.

### 2.2 Creating a Journal Entry

1. **Accounting › Journal Entries › New Entry**.
2. Set **Journal Date** and **Reference** (e.g., OR number, vendor invoice number).
3. Add line items:
   - Each line: account code, description, Debit (Dr) or Credit (Cr) amount.
   - The system enforces $\sum Dr = \sum Cr$ — you cannot submit an unbalanced entry.
4. Attach supporting documents (e.g., receipts, vouchers) in the **Attachments** tab.
5. Click **Submit**.

### 2.3 Approving / Rejecting

1. Navigate to **Accounting › Journal Entries** → filter **Status = Submitted**.
2. Open the entry — review lines, amounts, and attachments.
3. **Post**: click **Post** — the entry is committed. GL account balances update immediately.
4. **Reject**: click **Reject**, enter rejection reason — entry returns to **draft** for the preparer.

> **Segregation of Duties:** The user who created the entry cannot post it. The system enforces this programmatically.

### 2.4 Reversing a Posted Entry

1. Open a posted entry → **⋮ Actions → Reverse**.
2. Select the **reversal date** (typically the first day of the next period).
3. Confirm — the system creates a mirror entry with all Dr/Cr swapped and posts it automatically.

---

## 3. Accounts Payable (AP) Workflow

### 3.1 Vendor Management

1. **AP › Vendors › New Vendor**.
2. Required fields: legal name, TIN, address, BIR form type (2307 / Regular), payment terms, bank details.
3. Click **Save** — vendor is active immediately.

### 3.2 Recording a Vendor Invoice

1. **AP › Invoices › New Invoice**.
2. Select vendor and enter:
   - **Invoice Date**, **Invoice Number** (vendor's reference), **Due Date**.
   - **Line Items**: description, amount, VAT classification (VATable / VAT-exempt / Zero-rated).
3. The system automatically computes:
   - **Input VAT** (12% of VATable lines) → debit **Input Tax** (1-130-100).
   - **Net of VAT** amount → debit correct expense/asset account.
   - **Accounts Payable** → credit full invoice amount.
4. Click **Save** — the invoice is in **Open** status.

### 3.3 Matching Invoices to POs

If integrated with procurement:

1. On the invoice form → **Match PO** tab.
2. Select the Purchase Order.
3. The system flags any **quantity or price variance** for review.
4. Approve or reject the match.

### 3.4 Recording a Payment

1. **AP › Invoices** → open an unpaid invoice → **Record Payment**.
2. Fill in: payment date, bank account, check number (or payment reference), and amount.
3. If partial payment: the invoice status changes to **Partial**; the remaining balance stays open.
4. On full payment: status → **Paid**.
5. The system posts:
   - Dr **Accounts Payable** (reduces liability)
   - Cr **Cash / Bank** (reduces asset)

### 3.5 Vendor Aging Report

**AP › Reports › Aging** — outstanding invoices bucketed by days past due (Current / 1–30 / 31–60 / 61–90 / 90+).

---

## 4. Accounts Receivable (AR) Workflow

### 4.1 Customer Invoice

1. **AR › Invoices › New Invoice** → select customer, add line items with VAT.
2. The system posts:
   - Dr **Accounts Receivable**
   - Cr **Revenue** + **Output VAT**

### 4.2 Recording a Collection

1. **AR › Invoices** → open invoice → **Record Payment**.
2. Enter: collection date, bank account, OR number, amount.
3. System posts:
   - Dr **Cash / Bank**
   - Cr **Accounts Receivable**

### 4.3 Customer Aging Report

**AR › Reports › Aging** — same bucket structure as AP aging.

---

## 5. Bank Reconciliation

### 5.1 Process

1. **Accounting › Bank Reconciliation › New Reconciliation**.
2. Select **Bank Account** and **Statement Period**.
3. Enter the **Statement Ending Balance** (from the bank statement).
4. The system loads all uncleared transactions (payments and collections).
5. Tick the checkbox next to each transaction that appears on the bank statement.
6. The **Reconciling Difference** must reach **₱0.00** before you can close.
7. Common reconciling items:
   - Outstanding checks — issued but not yet cleared by the bank.
   - Deposits in transit — collected but not yet credited.
   - Bank charges — debited by the bank, not yet recorded in the ERP.
8. Record any bank-only items (charges, interest income) via **Manual Bank Entry**.
9. Click **Post Reconciliation** — the period is locked.

---

## 6. Monthly Period Close Procedure

Run in this sequence **on the last working day of the month**:

| Step | Action | Module |
|------|--------|--------|
| 1 | Ensure all AP invoices for the month are entered | AP › Invoices |
| 2 | Ensure all AR invoices for the month are entered | AR › Invoices |
| 3 | Post all approved journal entries | Accounting › Journal Entries |
| 4 | Approve payroll run (if month-end coincides with payroll) | Payroll › Runs |
| 5 | Complete bank reconciliation for all bank accounts | Accounting › Bank Reconciliation |
| 6 | Review trial balance for unusual balances | Accounting › Reports › Trial Balance |
| 7 | Make accrual / prepaid adjusting entries if any | Accounting › Journal Entries |
| 8 | Generate financial statements and review | Accounting › Reports |
| 9 | **Lock the fiscal period** | Accounting › Fiscal Periods → Close |

> **Warning:** Once a fiscal period is **Closed**, no new transactions can be posted to it. Only `admin` can reopen a closed period.

---

## 7. Financial Reports

All reports can be filtered by date range or fiscal period and exported to **PDF** or **CSV**.

### 7.1 General Ledger

**Accounting › Reports › General Ledger**

- Shows every transaction per account for the period.
- Filter by account code range, department, or reference.

### 7.2 Trial Balance

**Accounting › Reports › Trial Balance**

- Summarised Dr and Cr totals per account.
- Must show $\sum Dr = \sum Cr$ — any imbalance indicates an unposted or erroneous entry.
- Run this before closing a period.

### 7.3 Balance Sheet

**Accounting › Reports › Balance Sheet**

Presents financial position as of a date:

$$\text{Assets} = \text{Liabilities} + \text{Equity}$$

Key sections: Current Assets, Non-current Assets, Current Liabilities, Long-term Liabilities, Stockholders' Equity.

### 7.4 Income Statement (P&L)

**Accounting › Reports › Income Statement**

$$\text{Net Income} = \text{Revenue} - \text{COGS} - \text{Operating Expenses} \pm \text{Other Income/Expense}$$

Available as month-to-date, quarter-to-date, or year-to-date.

### 7.5 Cash Flow Statement

**Accounting › Reports › Cash Flow**

Direct method: operating activities (collections and payments), investing activities, financing activities.

---

## 8. VAT Compliance

### 8.1 Monthly VAT Relief Computation

1. **Accounting › Tax › VAT Summary** → select month.
2. The system aggregates:
   - **Output VAT** (from AR invoices and other revenue).
   - **Input VAT** (from AP invoices, importations).
3. VAT payable:

$$\text{VAT Payable} = \text{Output VAT} - \text{Input VAT}$$

### 8.2 BIR Form 2550M (Monthly VAT Return)

1. Click **Generate 2550M** → review computed figures.
2. Reconcile with the system totals.
3. The report is exported in the BIR e-Filing format.

### 8.3 Expanded Withholding Tax (EWT) — BIR Form 2307

1. **AP › Invoices** — select invoices where the vendor is subject to EWT.
2. The EWT amount is computed as: `Invoice Net × EWT Rate` (per vendor classification).
3. Monthly: **Accounting › Tax › EWT Summary** → export for 1601-EQ filing.

---

## Quick Reference: Key Month-End Journal Entries

| Entry | Dr | Cr |
|-------|----|----|
| SSS/PhilHealth/PagIBIG Employer share (payroll) | Payroll Expense | SSS/PhilHealth/HDMF Payable |
| Withholding tax on salaries | Salaries Payable | BIR Withholding Tax Payable |
| Depreciation | Depreciation Expense | Accumulated Depreciation |
| Prepaid insurance amortization | Insurance Expense | Prepaid Insurance |
| Accrued expenses | Expense account | Accrued Liabilities |

---

*For technical issues or access problems, contact your system administrator.*
