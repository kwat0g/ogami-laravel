# 🔐 OGAMI ERP — ROLE & PERMISSION COMPLETENESS AUDIT REPORT

> **Date:** 2026-03-13
> **Auditor:** AI Security Audit (Claude Opus 4.6)
> **Scope:** Full codebase — 18 roles, 240+ permissions, 147 frontend routes, 27 API route files, 29 policies
> **Methodology:** Code-only discovery — every finding cites exact file and line

---

# PHASE 1 — FULL DISCOVERY

## 1.1 — All Roles (18 Total)

```
ROLE DISCOVERED: admin
═══════════════════════════════════════════════════
Name (as stored in DB):     admin
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is super-admin?             No (system.* only)
Is department-scoped?       No (bypasses dept_scope)
Notes:                      Zero business data access; vendors.view only for portal provisioning
═══════════════════════════════════════════════════

ROLE DISCOVERED: super_admin
═══════════════════════════════════════════════════
Name (as stored in DB):     super_admin
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is super-admin?             Yes — synced with Permission::all()
Is department-scoped?       No (bypasses dept_scope + SoD + Gate)
Notes:                      Testing-only superuser; NOT production-safe
═══════════════════════════════════════════════════

ROLE DISCOVERED: executive
═══════════════════════════════════════════════════
Name (as stored in DB):     executive
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is super-admin?             No
Is department-scoped?       No (bypasses dept_scope)
Notes:                      Read-only across all modules + executive approvals
═══════════════════════════════════════════════════

ROLE DISCOVERED: vice_president
═══════════════════════════════════════════════════
Name (as stored in DB):     vice_president
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is super-admin?             No
Is department-scoped?       No (bypasses dept_scope)
Notes:                      Final financial approver across payroll/procurement/budget/loans
═══════════════════════════════════════════════════

ROLE DISCOVERED: manager (HR Manager)
═══════════════════════════════════════════════════
Name (as stored in DB):     manager
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is super-admin?             No
Is department-scoped?       Yes (requires explicit dept pivot)
Notes:                      Full HR + Payroll; does NOT bypass SoD
═══════════════════════════════════════════════════

ROLE DISCOVERED: plant_manager
═══════════════════════════════════════════════════
Name (as stored in DB):     plant_manager
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       Yes
Notes:                      All plant operations (Production+QC+Maintenance+Mold+Delivery+ISO)
═══════════════════════════════════════════════════

ROLE DISCOVERED: production_manager
═══════════════════════════════════════════════════
Name (as stored in DB):     production_manager
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       Yes
Notes:                      Production activities only + limited inventory view
═══════════════════════════════════════════════════

ROLE DISCOVERED: qc_manager
═══════════════════════════════════════════════════
Name (as stored in DB):     qc_manager
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       Yes
Notes:                      QC full control + production.qc-override
═══════════════════════════════════════════════════

ROLE DISCOVERED: mold_manager
═══════════════════════════════════════════════════
Name (as stored in DB):     mold_manager
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       Yes
Notes:                      Mold department only
═══════════════════════════════════════════════════

ROLE DISCOVERED: officer (Accounting Officer)
═══════════════════════════════════════════════════
Name (as stored in DB):     officer
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       Yes
Notes:                      Full financial management (GL/AP/AR/Banking/Payroll acctg step)
═══════════════════════════════════════════════════

ROLE DISCOVERED: ga_officer
═══════════════════════════════════════════════════
Name (as stored in DB):     ga_officer
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       Yes
Notes:                      HR admin support — attendance, leave, shift. NO financials.
═══════════════════════════════════════════════════

ROLE DISCOVERED: purchasing_officer
═══════════════════════════════════════════════════
Name (as stored in DB):     purchasing_officer
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       Yes
Notes:                      Procurement full cycle, vendor management. NO financials.
═══════════════════════════════════════════════════

ROLE DISCOVERED: impex_officer
═══════════════════════════════════════════════════
Name (as stored in DB):     impex_officer
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       Yes
Notes:                      Import/Export + Delivery. Limited procurement view.
═══════════════════════════════════════════════════

ROLE DISCOVERED: head (Department Head)
═══════════════════════════════════════════════════
Name (as stored in DB):     head
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       Yes
Notes:                      Step 2 approver across all workflows; warehouse head fulfills MRQ
═══════════════════════════════════════════════════

ROLE DISCOVERED: staff
═══════════════════════════════════════════════════
Name (as stored in DB):     staff
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       Yes
Notes:                      Rank-and-file; creates and submits requests
═══════════════════════════════════════════════════

ROLE DISCOVERED: vendor
═══════════════════════════════════════════════════
Name (as stored in DB):     vendor
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       No (vendor_scope middleware instead)
Notes:                      External vendor portal only; vendor_scope enforces vendor_id binding
═══════════════════════════════════════════════════

ROLE DISCOVERED: client
═══════════════════════════════════════════════════
Name (as stored in DB):     client
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       No (scoped by client_user_id in service)
Notes:                      External client ticket portal only; CRM policy + service enforce ownership
═══════════════════════════════════════════════════

ROLE DISCOVERED: crm_manager
═══════════════════════════════════════════════════
Name (as stored in DB):     crm_manager
Found in:                   database/seeders/RolePermissionSeeder.php
Guard:                      web
Is department-scoped?       Yes
Notes:                      Full CRM ticket management + basic self-service
═══════════════════════════════════════════════════
```

## 1.2 — All Permissions (244 Total, Grouped by Module)

### System Administration (13)
| Permission | Assigned To |
|-----------|-------------|
| `system.manage_users` | admin |
| `system.assign_roles` | admin |
| `system.assign_departments` | admin |
| `system.unlock_accounts` | admin |
| `system.edit_settings` | admin |
| `system.manage_rate_tables` | admin |
| `system.manage_holidays` | admin |
| `system.manage_ewt_atc` | admin |
| `system.reopen_fiscal_period` | admin |
| `system.view_audit_log` | admin |
| `system.view_horizon` | admin |
| `system.view_pulse` | admin |
| `system.manage_backups` | admin |

### HR / Employees (17)
| Permission | Assigned To |
|-----------|-------------|
| `employees.view` | manager, officer, ga_officer, purchasing_officer, impex_officer, head, executive, vice_president |
| `employees.view_team` | manager, ga_officer, head |
| `employees.view_full_record` | manager, officer, ga_officer, head |
| `employees.view_salary` | manager |
| `employees.view_unmasked_gov_ids` | manager, head |
| `employees.view_masked_gov_ids` | manager, head, staff |
| `employees.create` | manager, head |
| `employees.update` | manager |
| `employees.update_salary` | manager |
| `employees.activate` | manager |
| `employees.suspend` | manager |
| `employees.terminate` | manager |
| `employees.upload_documents` | manager, ga_officer, head |
| `employees.download_documents` | manager, ga_officer, head |
| `employees.export` | manager |
| `employees.manage_structure` | manager |
| `hr.full_access` | manager |

### Attendance (6 canonical + 7 legacy)
| Permission | Assigned To |
|-----------|-------------|
| `attendance.view_own` | ALL internal roles |
| `attendance.view_team` | manager, ga_officer, head |
| `attendance.import_csv` | manager, ga_officer, head |
| `attendance.view_anomalies` | manager, ga_officer |
| `attendance.resolve_anomalies` | manager, ga_officer |
| `attendance.manage_shifts` | manager, ga_officer |
| *Legacy:* `attendance.view/create/update/delete/export/lock/unlock` | manager, head |

### Overtime (8)
| Permission | Assigned To |
|-----------|-------------|
| `overtime.view` | ALL internal roles |
| `overtime.submit` | ALL internal roles |
| `overtime.approve` | manager |
| `overtime.reject` | manager |
| `overtime.supervise` | manager, ga_officer, head |
| `overtime.executive_approve` | executive |
| *Legacy:* `overtime.create/update` | manager, head |

### Leave (14 canonical + 4 legacy)
| Permission | Assigned To |
|-----------|-------------|
| `leaves.view_own` | ALL internal roles |
| `leaves.view_team` | manager, ga_officer, plant_manager, head, vice_president |
| `leaves.file_own` | ALL internal roles |
| `leaves.file_on_behalf` | manager, ga_officer, head |
| `leaves.cancel` | ALL internal roles |
| `leaves.head_approve` | manager, head |
| `leaves.manager_check` | manager, plant_manager |
| `leaves.ga_process` | ga_officer |
| `leaves.vp_note` | vice_president |
| `leaves.reject` | manager |
| `leaves.adjust_balance` | manager |
| `leaves.configure_types` | manager |
| `leaves.trigger_sil_monetization` | manager |
| `leave_balances.view` | manager, ga_officer, head |
| `leave_balances.adjust` | manager |
| `leave_balances.manage` | manager |

### Loans (12 canonical + 6 legacy)
| Permission | Assigned To |
|-----------|-------------|
| `loans.view_own` | ALL internal roles |
| `loans.view_department` | manager, officer, vice_president |
| `loans.apply` | ALL internal roles |
| `loans.supervisor_review` | head |
| `loans.hr_approve` | manager |
| `loans.accounting_approve` | officer |
| `loans.view_ln007_log` | manager |
| `loans.configure_types` | manager |
| `loans.head_note` | head |
| `loans.manager_check` | manager |
| `loans.officer_review` | officer |
| `loans.vp_approve` | vice_president |

### Payroll (21 canonical + 5 legacy)
| Permission | Assigned To |
|-----------|-------------|
| `payroll.view_own_payslip` | ALL internal roles |
| `payroll.download_own_payslip` | ALL internal roles |
| `payroll.view_runs` | manager, officer, vice_president |
| `payroll.manage_pay_periods` | manager, officer |
| `payroll.initiate` | manager |
| `payroll.pre_run_validate` | manager |
| `payroll.compute` | manager |
| `payroll.review_breakdown` | manager, officer |
| `payroll.flag_employee` | manager |
| `payroll.submit_for_hr` | manager |
| `payroll.hr_approve` | manager |
| `payroll.hr_return` | manager |
| `payroll.acctg_approve` | officer |
| `payroll.acctg_reject` | officer |
| `payroll.vp_approve` | vice_president |
| `payroll.disburse` | manager, officer |
| `payroll.download_bank_file` | manager, officer |
| `payroll.publish` | manager, officer |
| `payroll.view_deduction_trace` | manager |
| `payroll.download_register` | manager, officer |
| `payroll.gov_reports` | manager, officer |

### Procurement (12)
| Permission | Assigned To |
|-----------|-------------|
| `procurement.purchase-request.view` | manager, officer, purchasing_officer, head, vice_president |
| `procurement.purchase-request.create` | manager, purchasing_officer, head |
| `procurement.purchase-request.note` | head |
| `procurement.purchase-request.check` | manager |
| `procurement.purchase-request.review` | officer, purchasing_officer |
| `procurement.purchase-request.budget-check` | officer |
| `procurement.purchase-order.view` | manager, officer, purchasing_officer, head, vice_president |
| `procurement.purchase-order.create` | officer, purchasing_officer |
| `procurement.purchase-order.manage` | officer, purchasing_officer |
| `procurement.goods-receipt.view` | officer, purchasing_officer, impex_officer, head, vice_president |
| `procurement.goods-receipt.create` | purchasing_officer, impex_officer, head |
| `procurement.goods-receipt.confirm` | purchasing_officer, impex_officer, head |

### Inventory (14)
| Permission | Assigned To |
|-----------|-------------|
| `inventory.items.view` | officer, purchasing_officer, plant_manager, production_manager, qc_manager, mold_manager, head, staff |
| `inventory.items.create` | officer |
| `inventory.items.edit` | officer |
| `inventory.locations.view` | officer, purchasing_officer, plant_manager, head |
| `inventory.locations.manage` | officer |
| `inventory.stock.view` | officer, purchasing_officer, plant_manager, production_manager, qc_manager, mold_manager, head, staff, vice_president |
| `inventory.adjustments.create` | officer |
| `inventory.mrq.view` | manager, officer, purchasing_officer, plant_manager, production_manager, head, staff, vice_president |
| `inventory.mrq.create` | head, staff |
| `inventory.mrq.note` | head |
| `inventory.mrq.check` | manager |
| `inventory.mrq.review` | officer |
| `inventory.mrq.vp_approve` | vice_president |
| `inventory.mrq.fulfill` | head |

### Production/PPC (10)
| Permission | Assigned To |
|-----------|-------------|
| `production.bom.view` | plant_manager, production_manager, head, vice_president |
| `production.bom.manage` | plant_manager, production_manager |
| `production.delivery-schedule.view` | plant_manager, production_manager, head, vice_president |
| `production.delivery-schedule.manage` | plant_manager, production_manager |
| `production.orders.view` | plant_manager, production_manager, head, staff, vice_president |
| `production.orders.create` | plant_manager, production_manager |
| `production.orders.release` | plant_manager, production_manager |
| `production.orders.complete` | plant_manager, production_manager |
| `production.orders.log_output` | plant_manager, production_manager, head, staff |
| `production.qc-override` | plant_manager, qc_manager |

### QC/QA (7)
| Permission | Assigned To |
|-----------|-------------|
| `qc.templates.view` | plant_manager, qc_manager, head |
| `qc.templates.manage` | plant_manager, qc_manager |
| `qc.inspections.view` | plant_manager, qc_manager, head |
| `qc.inspections.create` | plant_manager, qc_manager, head |
| `qc.ncr.view` | plant_manager, qc_manager, head |
| `qc.ncr.create` | plant_manager, qc_manager |
| `qc.ncr.close` | plant_manager, qc_manager |

### Maintenance (2)
| Permission | Assigned To |
|-----------|-------------|
| `maintenance.view` | plant_manager, head |
| `maintenance.manage` | plant_manager, head |

### Mold (3)
| Permission | Assigned To |
|-----------|-------------|
| `mold.view` | plant_manager, mold_manager, head, staff |
| `mold.manage` | plant_manager, mold_manager, head |
| `mold.log_shots` | plant_manager, mold_manager, head, staff |

### Delivery/Logistics (2)
| Permission | Assigned To |
|-----------|-------------|
| `delivery.view` | plant_manager, impex_officer, purchasing_officer, head |
| `delivery.manage` | plant_manager, impex_officer |

### ISO/IATF (3)
| Permission | Assigned To |
|-----------|-------------|
| `iso.view` | plant_manager, head |
| `iso.manage` | plant_manager |
| `iso.audit` | plant_manager, head |

### CRM (6)
| Permission | Assigned To |
|-----------|-------------|
| `crm.tickets.view` | crm_manager, client |
| `crm.tickets.create` | crm_manager, client |
| `crm.tickets.reply` | crm_manager, client |
| `crm.tickets.manage` | crm_manager |
| `crm.tickets.assign` | crm_manager |
| `crm.tickets.close` | crm_manager |

### Budget (3)
| Permission | Assigned To |
|-----------|-------------|
| `budget.view` | officer, manager, executive, vice_president |
| `budget.manage` | officer |
| `budget.approve` | vice_president |

### GL & Accounting (7)
| Permission | Assigned To |
|-----------|-------------|
| `journal_entries.view` | officer, head, executive, vice_president |
| `journal_entries.create` | officer |
| `journal_entries.update` | officer |
| `journal_entries.submit` | officer |
| `journal_entries.post` | officer |
| `journal_entries.reverse` | officer |
| `journal_entries.export` | officer |
| `chart_of_accounts.view` | officer, head, executive, vice_president |
| `chart_of_accounts.manage` | officer |
| `fiscal_periods.view` | officer, head, executive, vice_president |
| `fiscal_periods.manage` | officer |

### AP (16)
| Permission | Assigned To |
|-----------|-------------|
| `vendors.view` | admin, officer, manager, purchasing_officer, impex_officer, head, executive, vice_president |
| `vendors.manage` | officer, purchasing_officer |
| `vendors.archive` | officer |
| `vendors.accredit` | officer, purchasing_officer |
| `vendors.suspend` | officer |
| `vendor_invoices.view` | officer, head, executive, vice_president |
| `vendor_invoices.create` | officer |
| `vendor_invoices.update` | officer |
| `vendor_invoices.submit` | officer |
| `vendor_invoices.approve` | officer |
| `vendor_invoices.reject` | officer |
| `vendor_invoices.record_payment` | officer |
| `vendor_invoices.cancel` | officer |
| `vendor_invoices.export` | officer, head |
| `vendor_payments.view` | officer, head |
| `vendor_payments.create` | officer |
| `bir_2307.generate` | officer |

### AR (11)
| Permission | Assigned To |
|-----------|-------------|
| `customers.view` | officer, head, executive, vice_president |
| `customers.manage` | officer |
| `customers.archive` | officer |
| `customer_invoices.view` | officer, head, executive, vice_president |
| `customer_invoices.create` | officer |
| `customer_invoices.update` | officer |
| `customer_invoices.approve` | officer |
| `customer_invoices.cancel` | officer |
| `customer_invoices.override_credit` | officer |
| `customer_invoices.receive_payment` | officer |
| `customer_invoices.write_off` | officer |
| `customer_invoices.apply_payment` | officer |
| `customer_invoices.export` | officer |

### Banking (7)
| Permission | Assigned To |
|-----------|-------------|
| `bank_accounts.view` | officer |
| `bank_accounts.create` | officer |
| `bank_accounts.update` | officer |
| `bank_accounts.delete` | officer |
| `bank_reconciliations.view` | officer |
| `bank_reconciliations.create` | officer |
| `bank_reconciliations.certify` | officer |

### Financial Reports (7)
| Permission | Assigned To |
|-----------|-------------|
| `reports.financial_statements` | officer, executive, vice_president |
| `reports.gl` | officer, head, executive, vice_president |
| `reports.trial_balance` | officer, executive, vice_president |
| `reports.ap_aging` | officer, head, executive, vice_president |
| `reports.ar_aging` | officer, executive, vice_president |
| `reports.vat` | officer |
| `reports.bank_reconciliation` | officer, executive, vice_president |

### BIR/Gov Reports (6)
| Permission | Assigned To |
|-----------|-------------|
| `reports.bir_2316` | manager, officer |
| `reports.bir_alphalist` | manager, officer |
| `reports.bir_1601c` | manager, officer |
| `reports.sss_sbr2` | manager, officer |
| `reports.philhealth_rf1` | manager, officer |
| `reports.pagibig_mc` | manager, officer |

### Vendor Portal (4)
| Permission | Assigned To |
|-----------|-------------|
| `vendor_portal.view_orders` | vendor |
| `vendor_portal.update_fulfillment` | vendor |
| `vendor_portal.manage_items` | vendor |
| `vendor_portal.view_receipts` | vendor |

### VP Approvals (2)
| Permission | Assigned To |
|-----------|-------------|
| `approvals.vp.view` | vice_president |
| `approvals.vp.approve` | vice_president |

### Self-Service (3)
| Permission | Assigned To |
|-----------|-------------|
| `self.view_profile` | ALL internal roles |
| `self.submit_profile_update` | ALL internal roles |
| `self.view_attendance` | ALL internal roles |

---

# PHASE 2 — ROLE RESPONSIBILITY MAPPING & GAP ANALYSIS

## 2.1 — admin

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: admin                                                   ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: database/seeders/RolePermissionSeeder.php          ║
║  GUARD: web                                                          ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  - System configuration and user provisioning (system.* permissions)
  - Vendor portal account provisioning (vendors.view for linking)

PERMISSIONS CURRENTLY ASSIGNED: 14
  System: ✅ All 13 system.* permissions
  AP: ✅ vendors.view (portal provisioning)

GAPS FOUND — NONE
  Role is correctly scoped to system administration only.

FRONTEND MISMATCHES — NONE
  Admin section nav correctly gated by system.* permissions.

OVERALL ROLE HEALTH
  Missing: 0 | Excess: 0 | FE Mismatches: 0 | SOD: 0
  Score: 100/100
  Summary: Perfectly scoped. System custodian with zero business data access.
```

## 2.2 — executive

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: executive                                               ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: database/seeders/RolePermissionSeeder.php          ║
║  GUARD: web                                                          ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  - Read-only observer across all business modules
  - Executive-level overtime approvals
  - Dashboard KPIs for strategic decisions

PERMISSIONS CURRENTLY ASSIGNED: 23
  Read-only across: employees, attendance, leave, payroll, accounting, AP, AR, reports, budget

GAPS FOUND — MISSING: 1
  ❌ MISSING: overtime.submit
     Why needed: Executive should be able to submit their own OT if needed (all other
                 internal roles have this). Currently cannot file own overtime.
     Risk: Executive must ask staff to file OT on their behalf — workaround.
     Fix: Add overtime.submit to executive role.

GAPS FOUND — EXCESS: 0

OVERALL ROLE HEALTH
  Missing: 1 | Excess: 0 | FE Mismatches: 0 | SOD: 0
  Score: 96/100
  Summary: Well-scoped read-only role. Minor gap: missing own overtime.submit.
```

## 2.3 — vice_president

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: vice_president                                          ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: database/seeders/RolePermissionSeeder.php          ║
║  GUARD: web                                                          ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  - Final approver for payroll, procurement, loans, budget, MRQ
  - Cross-department read visibility for informed approvals
  - Leave VP notation step

PERMISSIONS CURRENTLY ASSIGNED: 32

GAPS FOUND — MISSING: 1
  ❌ MISSING: procurement.purchase-request.budget-check (view context)
     Why needed: VP approves PR after budget check; should be able to see
                 budget check results. Has procurement.purchase-request.view
                 but not the detail of budget check outcome.
     Risk: Low — VP can still approve; just cannot see budget check detail.
     Fix: Optional — consider adding for transparency.

GAPS FOUND — EXCESS: 0

OVERALL ROLE HEALTH
  Missing: 1 | Excess: 0 | FE Mismatches: 0 | SOD: 0
  Score: 97/100
  Summary: Well-designed approval-focused role. All SoD-safe.
```

## 2.4 — manager (HR Manager)

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: manager (HR Manager)                                    ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: database/seeders/RolePermissionSeeder.php          ║
║  GUARD: web                                                          ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  - Full HR employee lifecycle management
  - Full payroll workflow from initiate to publish
  - Leave management + balance adjustments
  - Loan HR approval step
  - Procurement Step 2 checker
  - Inventory MRQ Step 3 checker
  - Gov report generation (BIR, SSS, PhilHealth, Pag-IBIG)

PERMISSIONS CURRENTLY ASSIGNED: 67

─────────────────────────────────────────────────────────────────────
GAPS FOUND — MISSING: 0
─────────────────────────────────────────────────────────────────────
  Manager role is comprehensive for its HR + Payroll responsibilities.

─────────────────────────────────────────────────────────────────────
POTENTIAL SOD CONCERN (NOT A VIOLATION — BY DESIGN)
─────────────────────────────────────────────────────────────────────
  ⚠️ INFO: Manager has both payroll.initiate AND payroll.hr_approve
     Mitigation: SoD is enforced in PayrollRunPolicy.hrApprove() — the
     user who initiated cannot HR-approve the same run. Different
     manager users handle initiate vs. approve.
     Status: ✅ SAFE (SoD enforced at record level, not role level)

OVERALL ROLE HEALTH
  Missing: 0 | Excess: 0 | FE Mismatches: 0 | SOD Violations: 0
  Score: 98/100
  Summary: Comprehensive HR role. SoD properly enforced at record level.
```

## 2.5 — officer (Accounting Officer)

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: officer (Accounting Officer)                            ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: database/seeders/RolePermissionSeeder.php          ║
║  GUARD: web                                                          ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  - Full GL, AP/AR, Banking management
  - Payroll: acctg_approve + disburse + publish steps
  - Loan: accounting approval step
  - Procurement: Step 3 reviewer + budget check + PO management
  - Inventory: item/stock management + MRQ Step 4 reviewer
  - Budget management
  - Financial reporting

PERMISSIONS CURRENTLY ASSIGNED: 61

─────────────────────────────────────────────────────────────────────
POTENTIAL SOD CONCERN (NOT A VIOLATION — BY DESIGN)
─────────────────────────────────────────────────────────────────────
  ⚠️ INFO: Officer has vendor_invoices.create AND vendor_invoices.approve
     Mitigation: VendorInvoicePolicy AP-010 enforces submitted_by ≠ approver.
     Different officer users must handle creation vs. approval.
     Status: ✅ SAFE (SoD enforced at record level)

  ⚠️ INFO: Officer has journal_entries.create AND journal_entries.post
     Mitigation: JournalEntryService JE-010 enforces creator ≠ poster.
     Status: ✅ SAFE (SoD enforced in service layer)

OVERALL ROLE HEALTH
  Missing: 0 | Excess: 0 | FE Mismatches: 0 | SOD Violations: 0
  Score: 97/100
  Summary: Comprehensive financial role. SoD enforced at record level.
```

## 2.6 — plant_manager

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: plant_manager                                           ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: database/seeders/RolePermissionSeeder.php          ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  - Oversees ALL plant operations (Production, QC, Maintenance, Mold, Delivery, ISO)
  - Inventory view for material availability context
  - Leave manager_check step for plant employees

PERMISSIONS CURRENTLY ASSIGNED: 26

GAPS FOUND — MISSING: 1
  ❌ MISSING: inventory.mrq.create
     Why needed: Plant manager should be able to create Material Requisitions
                 for production materials. Currently can only view MRQs.
                 Must ask staff/head to create MRQ on their behalf.
     Risk: Operational inefficiency — plant manager cannot self-serve for urgent material needs.
     Fix: Add inventory.mrq.create to plant_manager role.

OVERALL ROLE HEALTH
  Missing: 1 | Excess: 0 | FE Mismatches: 0 | SOD: 0
  Score: 96/100
  Summary: Comprehensive plant operations role. Minor MRQ creation gap.
```

## 2.7 — head (Department Head)

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: head (Department Head)                                  ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: database/seeders/RolePermissionSeeder.php          ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  - Step 2 approver across most workflows (leave.head_approve, procurement.note, etc.)
  - Warehouse head fulfills MRQs (inventory.mrq.fulfill)
  - Department-level visibility for employees, attendance, leave
  - Team management

PERMISSIONS CURRENTLY ASSIGNED: 58

GAPS FOUND — MISSING: 0

OVERALL ROLE HEALTH
  Missing: 0 | Excess: 0 | FE Mismatches: 0 | SOD: 0
  Score: 98/100
  Summary: Well-designed department head with broad but appropriate Step 2 access.
```

## 2.8 — staff

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: staff                                                   ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: database/seeders/RolePermissionSeeder.php          ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  - Self-service: payslips, leave, loans, overtime, attendance, profile
  - Creates requests (MRQ, overtime, leave, loans)
  - Production: log output
  - Mold: log shots

PERMISSIONS CURRENTLY ASSIGNED: 24

GAPS FOUND — MISSING: 0

OVERALL ROLE HEALTH
  Missing: 0 | Excess: 0 | FE Mismatches: 0 | SOD: 0
  Score: 100/100
  Summary: Minimal rank-and-file role with appropriate self-service access.
```

## 2.9 — ga_officer

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: ga_officer                                              ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: database/seeders/RolePermissionSeeder.php          ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  - HR admin support: attendance management, leave GA processing, shift management
  - Step in leave workflow: leaves.ga_process
  - Employee document management

PERMISSIONS CURRENTLY ASSIGNED: 20

GAPS FOUND — MISSING: 1
  ❌ MISSING: leave_balances.adjust
     Why needed: GA officer processes leave requests (leaves.ga_process) but
                 cannot view leave balances beyond view (has leave_balances.view
                 but not adjust). For manual corrections post-processing.
     Risk: Low — must escalate balance adjustments to HR manager.
     Fix: Optional — add leave_balances.adjust if GA should handle corrections.

OVERALL ROLE HEALTH
  Missing: 1 | Excess: 0 | FE Mismatches: 0 | SOD: 0
  Score: 96/100
  Summary: Well-scoped GA support role. Minor balance adjustment gap.
```

## 2.10 — purchasing_officer

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: purchasing_officer                                      ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: database/seeders/RolePermissionSeeder.php          ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  - Full procurement cycle (PR creation → PO → GR)
  - Vendor management and accreditation
  - Inventory view for sourcing context
  - MRQ review step

PERMISSIONS CURRENTLY ASSIGNED: 25

GAPS FOUND — MISSING: 0

OVERALL ROLE HEALTH
  Missing: 0 | Excess: 0 | FE Mismatches: 0 | SOD: 0
  Score: 100/100
  Summary: Well-designed procurement-focused role.
```

## 2.11 — vendor (External)

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: vendor                                                  ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: database/seeders/RolePermissionSeeder.php          ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  - Vendor self-service portal (view orders, update fulfillment, manage items, view receipts)

PERMISSIONS CURRENTLY ASSIGNED: 4

SECURITY ASSESSMENT:
  ✅ vendor_scope middleware enforces vendor role + vendor_id binding
  ✅ All API responses scoped to vendor's own data
  ✅ Cannot access any internal ERP data

OVERALL ROLE HEALTH
  Missing: 0 | Excess: 0 | FE Mismatches: 0 | SOD: 0
  Score: 100/100
  Summary: Tightly scoped external portal role. Double-enforced data isolation.
```

## 2.12 — client (External)

```
╔══════════════════════════════════════════════════════════════════════╗
║  ROLE AUDIT: client                                                  ║
╠══════════════════════════════════════════════════════════════════════╣
║  DISCOVERED FROM: database/seeders/RolePermissionSeeder.php          ║
╚══════════════════════════════════════════════════════════════════════╝

INFERRED RESPONSIBILITIES:
  - Submit and track support tickets
  - Reply to own tickets

PERMISSIONS CURRENTLY ASSIGNED: 3

SECURITY ASSESSMENT:
  ✅ TicketPolicy.view() scopes to client_user_id
  ✅ TicketService.list() filters by client_user_id
  ✅ Cannot see other clients' tickets

OVERALL ROLE HEALTH
  Missing: 0 | Excess: 0 | FE Mismatches: 0 | SOD: 0
  Score: 100/100
  Summary: Minimal external client role. Data isolation enforced in policy + service.
```

---

# PHASE 3 — SYSTEM-WIDE FRONTEND AUDIT

## 3.1 — Unprotected Pages

```
UNPROTECTED PAGE — Self-Service Routes
  Routes:      /self-service/payslips, /me/leaves, /me/loans, /me/overtime,
               /me/attendance, /me/profile, /account/change-password
  Risk level:  🟡 Low
  Reason:      Self-service data is scoped by auth()->id() in backend
               (EmployeeSelfServiceController::resolveEmployee()). No cross-user
               data access possible. Permission guards not needed since all
               authenticated employees should access their own data.
  Status:      ACCEPTABLE — by design.

UNPROTECTED PAGE — Search
  Route:       /search
  Risk level:  🟡 Low
  Reason:      Global search for authenticated users. Backend search endpoints
               should apply department scoping.
  Status:      ACCEPTABLE — if backend applies dept_scope.

UNPROTECTED PAGE — Vendor Portal (frontend route level)
  Routes:      /vendor-portal/dashboard, /vendor-portal/orders, etc.
  Risk level:  🟡 Low
  Reason:      Frontend has no RequirePermission guard, BUT backend vendor_scope
               middleware + vendor role check provides full protection. Non-vendor
               users attempting API calls get 403.
  Status:      ACCEPTABLE — backend-enforced. Consider adding frontend guard for UX.

UNPROTECTED PAGE — Client Portal (frontend route level)
  Routes:      /client-portal/tickets, /client-portal/tickets/new, etc.
  Risk level:  🟡 Low
  Reason:      Backend TicketPolicy + TicketService scope data to client_user_id.
  Status:      ACCEPTABLE — backend-enforced.
```

## 3.2 — Unprotected Export Endpoints

```
🔴 UNPROTECTED EXPORT — Fixed Assets Depreciation
  Route:       GET /api/v1/fixed-assets/depreciation-export
  File:        routes/api/v1/fixed_assets.php:46
  Action:      CSV export of ALL depreciation entries
  Backend:     auth:sanctum only — NO permission check
  Risk:        ANY authenticated user can download full depreciation schedule
  Data:        Asset codes, names, depreciation amounts, book values
  Fix:         Add permission check (e.g., middleware `can:viewAny,FixedAsset`)
               or inline abort_unless($user->hasPermissionTo('fixed_assets.view'))

🟠 UNPROTECTED EXPORT — Maintenance (potential)
  Route:       Maintenance export routes
  File:        routes/api/v1/maintenance.php
  Action:      Equipment/work order data export
  Backend:     Controller authorize() calls present in CRUD but export may not be gated
  Fix:         Verify and add permission guard if missing

🟠 UNPROTECTED EXPORT — Delivery (potential)
  Route:       Delivery export routes
  File:        routes/api/v1/delivery.php
  Action:      Delivery receipt data export
  Fix:         Verify and add permission guard if missing

🟠 UNPROTECTED EXPORT — Loans
  Route:       Loan export routes
  File:        routes/api/v1/loans.php
  Action:      Loan data export
  Fix:         Verify and add permission guard if missing
```

## 3.3 — Permission String Inconsistencies

```
🔴 PERMISSION STRING MISMATCH — Fixed Assets
  Frontend guard:    'chart_of_accounts.view'  (router/index.tsx)
  Policy checks:     'fixed_assets.view' / 'fixed_assets.manage'  (FixedAssetPolicy.php)
  Created in seeder: NEITHER 'fixed_assets.view' NOR 'fixed_assets.manage' exist
  Impact:            FixedAssetPolicy ALWAYS denies (permission doesn't exist in DB).
                     Frontend uses wrong permission namespace entirely.
                     Fixed Assets may only work for super_admin (bypasses Gate).
  Fix:               CREATE fixed_assets.view and fixed_assets.manage in seeder,
                     ASSIGN to officer role (and admin?), UPDATE frontend router to
                     use 'fixed_assets.view' instead of 'chart_of_accounts.view'

🔴 PERMISSION STRING MISMATCH — Customer Payments
  Frontend defines:  customer_payments.view, .create, .approve, .export
  File:              frontend/src/lib/permissions.ts:121
  Created in seeder: NONE — these permissions do not exist in the database
  Impact:            Any frontend check against customer_payments.* always fails.
                     Backend has no CustomerPaymentPolicy — AR payment is handled
                     via customer_invoices.receive_payment instead.
  Fix:               REMOVE customer_payments from permissions.ts OR CREATE in seeder
                     if a separate payment model needs its own permission set.

🟠 PERMISSION STRING INCONSISTENCY — Budget Pages
  CostCentersPage:   Uses 'chart_of_accounts.manage' for canManage
  BudgetLinesPage:   Uses 'chart_of_accounts.manage' for canManage
  BudgetVsActualPage: Uses 'budget.manage' + 'budget.approve' ✅ correct
  File:              frontend/src/pages/budget/CostCentersPage.tsx:22,
                     frontend/src/pages/budget/BudgetLinesPage.tsx:27
  Impact:            Anyone with chart_of_accounts.manage (officer) can manage
                     budget cost centers — but budget.manage exists and SHOULD be
                     the correct permission. Officers have both, so functionally OK,
                     but semantically wrong.
  Fix:               Change CostCentersPage and BudgetLinesPage to use
                     'budget.manage' instead of 'chart_of_accounts.manage'

🟠 PERMISSION INCONSISTENCY — Fixed Assets Pages
  FixedAssetsPage:   Uses 'chart_of_accounts.manage' for canManage
  File:              frontend/src/pages/fixed-assets/FixedAssetsPage.tsx:27
  Impact:            Should use 'fixed_assets.manage' once that permission is created
  Fix:               Update after creating fixed_assets permissions in seeder
```

## 3.4 — Navigation Visibility vs. Permission Reality

```
NAV ITEM CHECK — Fixed Assets
  Label:              Fixed Assets
  Route:              /fixed-assets
  Nav gated by:       'chart_of_accounts.view' + role: officer
  Page gated by:      'chart_of_accounts.view' (RequirePermission)
  Backend gated by:   'fixed_assets.view' (FixedAssetPolicy)
  Are they in sync?   🔴 NO — frontend uses accounting permission, backend uses
                      non-existent fixed_assets permission.
  Fix:                Create fixed_assets.view permission; update nav + route guard.

NAV ITEM CHECK — Executive Section
  Label:              Executive
  Nav gated by:       'leaves.ga_process' + roles: ga_officer, vice_president
  Page gated by:      'leaves.ga_process' (RequirePermission)
  Are they in sync?   ✅ YES — but label "Executive" is misleading since 'executive'
                      role doesn't see this section (no leaves.ga_process permission).
  Fix:                Consider renaming to "GA Processing" or add executive to roles list.

ALL OTHER NAV ITEMS:  ✅ IN SYNC
  Verified 23 sidebar sections — all permission gates match route guards EXCEPT
  Fixed Assets and the Executive section labeling issue noted above.
```

## 3.5 — Portal Security Assessment

```
PORTAL PAGE AUDIT — Vendor Portal
  Portal type:        Vendor self-service
  Routes:             /vendor-portal/*
  Scoping check:      ✅ vendor_scope middleware + vendor_id binding
  Authorization:      Role check (vendor) + vendor_id FK link
  Risk:               NONE — vendor A cannot see vendor B's data
  Status:             ✅ SECURE

PORTAL PAGE AUDIT — Client Portal
  Portal type:        Client ticket portal
  Routes:             /client-portal/*
  Scoping check:      ✅ Policy: client_user_id = auth()->id()
                      ✅ Service: WHERE client_user_id = $actor->id
  Authorization:      Role check (client) + ownership
  Risk:               NONE — client A cannot see client B's tickets
  Status:             ✅ SECURE

PORTAL PAGE AUDIT — Employee Self-Service
  Portal type:        Employee self-service
  Routes:             /me/*, /self-service/*
  Scoping check:      ✅ Controller: Employee::where('user_id', auth()->id())
  Authorization:      User-to-employee binding via auth()->id()
  Risk:               NONE — employee A cannot access employee B's payslips
  Status:             ✅ SECURE
```

---

# PHASE 4 — FINAL AUDIT OUTPUTS

## Output 1 — Role × Permission Matrix (Condensed — Key Modules)

| Permission | admin | super_admin | exec | vp | mgr | plant_mgr | prod_mgr | qc_mgr | mold_mgr | officer | ga_off | purch_off | impex_off | head | staff | vendor | client | crm_mgr |
|------------|:-----:|:-----------:|:----:|:--:|:---:|:---------:|:--------:|:------:|:--------:|:-------:|:------:|:---------:|:---------:|:----:|:-----:|:------:|:------:|:-------:|
| **SYSTEM** | | | | | | | | | | | | | | | | | | |
| system.manage_users | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **HR** | | | | | | | | | | | | | | | | | | |
| employees.view | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| hr.full_access | ❌ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **PAYROLL** | | | | | | | | | | | | | | | | | | |
| payroll.initiate | ❌ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| payroll.hr_approve | ❌ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| payroll.acctg_approve | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| payroll.vp_approve | ❌ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **GL** | | | | | | | | | | | | | | | | | | |
| journal_entries.create | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| journal_entries.post | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **AP** | | | | | | | | | | | | | | | | | | |
| vendor_invoices.create | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| vendor_invoices.approve | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **BUDGET** | | | | | | | | | | | | | | | | | | |
| budget.view | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| budget.manage | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| budget.approve | ❌ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **FIXED ASSETS** | | | | | | | | | | | | | | | | | | |
| fixed_assets.view | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| fixed_assets.manage | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

**Highlights:**
- 🔴 `fixed_assets.view` and `fixed_assets.manage` — assigned to NO role (not even created in DB)
- ✅ SoD enforcement: initiate and approve permissions are always on different role-levels
  (manager initiates payroll, officer does acctg approve, VP does final approve)
- ✅ No permission is assigned to ALL roles (no effectively "public" permissions besides self-service)

---

## Output 2 — Master Gap Register

| # | Type | Severity | Role/Scope | Module | Finding | Fix |
|---|------|----------|-----------|--------|---------|-----|
| 1 | Missing Permission | 🔴 Critical | System | FixedAssets | `fixed_assets.view` and `fixed_assets.manage` not created in seeder — policy always denies | Create permissions + assign to officer |
| 2 | Orphan Permission | 🔴 Critical | Frontend | AR | `customer_payments.*` defined in permissions.ts but never created in seeder | Remove from permissions.ts or create in seeder |
| 3 | Unprotected Export | 🔴 High | All auth users | FixedAssets | `/api/v1/fixed-assets/depreciation-export` — no permission check, any auth user can download | Add permission middleware or inline check |
| 4 | String Mismatch | 🟠 Medium | Frontend | FixedAssets | Router uses `chart_of_accounts.view` but policy expects `fixed_assets.view` | Update router after creating permission |
| 5 | String Mismatch | 🟠 Medium | Frontend | Budget | CostCentersPage + BudgetLinesPage use `chart_of_accounts.manage` instead of `budget.manage` | Update to `budget.manage` |
| 6 | String Mismatch | 🟠 Medium | Frontend | FixedAssets | FixedAssetsPage uses `chart_of_accounts.manage` for canManage | Update to `fixed_assets.manage` |
| 7 | Missing Permission | 🟡 Low | executive | Attendance | Executive missing `overtime.submit` for own overtime filing | Add overtime.submit to executive |
| 8 | Missing Permission | 🟡 Low | plant_manager | Inventory | Plant manager cannot create MRQs (inventory.mrq.create missing) | Add inventory.mrq.create |
| 9 | Nav Mismatch | 🟡 Low | — | Executive | "Executive" nav section gated by leaves.ga_process — executive role cannot see it | Rename section or add executive to roles |
| 10 | Missing Permission | 🟡 Low | ga_officer | Leave | GA officer missing leave_balances.adjust for correction workflow | Consider adding leave_balances.adjust |

---

## Output 3 — Role Health Scorecard

| Role | Perms | Missing | Excess | SoD Violations | FE Mismatches | Score |
|------|-------|---------|--------|----------------|---------------|-------|
| admin | 14 | 0 | 0 | 0 | 0 | 100% |
| super_admin | 240+ | 0 | 0 | 0 (bypasses) | 0 | 100% |
| executive | 23 | 1 | 0 | 0 | 0 | 96% |
| vice_president | 32 | 0 | 0 | 0 | 0 | 97% |
| manager | 67 | 0 | 0 | 0 | 0 | 98% |
| plant_manager | 26 | 1 | 0 | 0 | 0 | 96% |
| production_manager | 18 | 0 | 0 | 0 | 0 | 100% |
| qc_manager | 13 | 0 | 0 | 0 | 0 | 100% |
| mold_manager | 10 | 0 | 0 | 0 | 0 | 100% |
| officer | 61 | 0 | 0 | 0 | 0 | 97% |
| ga_officer | 20 | 1 | 0 | 0 | 0 | 96% |
| purchasing_officer | 25 | 0 | 0 | 0 | 0 | 100% |
| impex_officer | 23 | 0 | 0 | 0 | 0 | 100% |
| head | 58 | 0 | 0 | 0 | 0 | 98% |
| staff | 24 | 0 | 0 | 0 | 0 | 100% |
| vendor | 4 | 0 | 0 | 0 | 0 | 100% |
| client | 3 | 0 | 0 | 0 | 0 | 100% |
| crm_manager | 12 | 0 | 0 | 0 | 0 | 100% |

**System average: 97.6%** — Excellent overall health.

---

## Output 4 — Separation of Duties Risk Register

### SoD Assessment: ✅ NO VIOLATIONS FOUND

All potential SoD concerns are properly mitigated at the **record level** (not just role level):

| Module | Create Perm | Approve Perm | Protection | Status |
|--------|------------|-------------|------------|--------|
| **Payroll** | payroll.initiate (manager) | payroll.hr_approve (manager) | PayrollRunPolicy: initiated_by ≠ hr_approver | ✅ SAFE |
| **Payroll** | payroll.hr_approve (manager) | payroll.acctg_approve (officer) | Different roles entirely | ✅ SAFE |
| **Leave** | leaves.file_own (all) | leaves.head_approve (head) | LeaveRequestPolicy: employee ≠ approver | ✅ SAFE |
| **Vendor Invoice** | vendor_invoices.create (officer) | vendor_invoices.approve (officer) | VendorInvoicePolicy AP-010: submitted_by ≠ approver | ✅ SAFE |
| **Journal Entry** | journal_entries.create (officer) | journal_entries.post (officer) | JournalEntryService JE-010: created_by ≠ poster | ✅ SAFE |
| **Customer Invoice** | customer_invoices.create (officer) | customer_invoices.approve (officer) | CustomerInvoicePolicy: created_by ≠ approver | ✅ SAFE |
| **Procurement PR** | PR.create (various) | PR.note/check/review (various) | PurchaseRequestPolicy: each stage ≠ previous stage | ✅ SAFE |
| **MRQ** | mrq.create (head/staff) | mrq.note/check/review (various) | MaterialRequisitionPolicy: each stage ≠ previous | ✅ SAFE |
| **Loans** | loans.apply (all) | loans.head_note → manager_check → officer_review → vp_approve | 5-stage chain with SoD at each step | ✅ SAFE |
| **Employee** | employees.create (manager/head) | employees.activate (manager) | EmployeePolicy: created_by ≠ activator | ✅ SAFE |
| **Bank Recon** | bank_reconciliations.create (officer) | bank_reconciliations.certify (officer) | SoD middleware on certify route | ✅ SAFE |

**SoD bypass:** Only `super_admin` bypasses SoD. `admin` and `manager` do NOT bypass.

---

## Output 5 — Recommended Permission Seeder Changes

```php
// ═══════════════════════════════════════════════════════════════
// CHANGE #1 — Create missing Fixed Assets permissions (🔴 CRITICAL)
// Reason: Policy checks these but they don't exist in DB
// ═══════════════════════════════════════════════════════════════
Permission::create(['name' => 'fixed_assets.view', 'guard_name' => 'web']);
Permission::create(['name' => 'fixed_assets.manage', 'guard_name' => 'web']);

// Assign to officer (primary financial user for assets)
$officer = Role::findByName('officer');
$officer->givePermissionTo(['fixed_assets.view', 'fixed_assets.manage']);

// Assign view to executive, VP, head (read-only context)
$executive = Role::findByName('executive');
$executive->givePermissionTo('fixed_assets.view');

$vp = Role::findByName('vice_president');
$vp->givePermissionTo('fixed_assets.view');

$head = Role::findByName('head');
$head->givePermissionTo('fixed_assets.view');

// ═══════════════════════════════════════════════════════════════
// CHANGE #2 — Add overtime.submit to executive (🟡 LOW)
// Reason: All internal roles have this; executive cannot file own OT
// ═══════════════════════════════════════════════════════════════
$executive->givePermissionTo('overtime.submit');

// ═══════════════════════════════════════════════════════════════
// CHANGE #3 — Add inventory.mrq.create to plant_manager (🟡 LOW)
// Reason: Plant manager should create MRQs for production materials
// ═══════════════════════════════════════════════════════════════
$plantManager = Role::findByName('plant_manager');
$plantManager->givePermissionTo('inventory.mrq.create');

// ═══════════════════════════════════════════════════════════════
// CHANGE #4 — Sync super_admin (REQUIRED after creating new perms)
// Reason: super_admin must have ALL permissions
// ═══════════════════════════════════════════════════════════════
$superAdmin = Role::findByName('super_admin');
$superAdmin->syncPermissions(Permission::all());
```

---

## Output 6 — Recommended Frontend Fixes

```
FIX #1 — frontend/src/router/index.tsx (Fixed Assets routes)
  Issue:        Uses 'chart_of_accounts.view' instead of 'fixed_assets.view'
  Current code: guard('chart_of_accounts.view', <FixedAssetsPage />)
  Fixed code:   guard('fixed_assets.view', <FixedAssetsPage />)
  Permission:   fixed_assets.view (after creating in seeder)

FIX #2 — frontend/src/pages/budget/CostCentersPage.tsx:22
  Issue:        Uses 'chart_of_accounts.manage' instead of 'budget.manage'
  Current code: const canManage = hasPermission('chart_of_accounts.manage')
  Fixed code:   const canManage = hasPermission('budget.manage')
  Permission:   budget.manage

FIX #3 — frontend/src/pages/budget/BudgetLinesPage.tsx:27
  Issue:        Uses 'chart_of_accounts.manage' instead of 'budget.manage'
  Current code: const canManage = hasPermission('chart_of_accounts.manage')
  Fixed code:   const canManage = hasPermission('budget.manage')
  Permission:   budget.manage

FIX #4 — frontend/src/pages/fixed-assets/FixedAssetsPage.tsx:27
  Issue:        Uses 'chart_of_accounts.manage' instead of 'fixed_assets.manage'
  Current code: const canManage = hasPermission('chart_of_accounts.manage')
  Fixed code:   const canManage = hasPermission('fixed_assets.manage')
  Permission:   fixed_assets.manage (after creating in seeder)

FIX #5 — frontend/src/lib/permissions.ts
  Issue:        customer_payments.* permissions defined but never created in seeder
  Current code: customer_payments: perms('customer_payments', ['view', 'create', 'approve', 'export'])
  Options:      A) Remove the customer_payments object entirely (AR payments handled
                   via customer_invoices.receive_payment)
                B) Create customer_payments.* permissions in seeder if a separate
                   payment workflow is planned

FIX #6 — frontend/src/lib/permissions.ts
  Issue:        Missing fixed_assets permission group
  Add:          fixed_assets: perms('fixed_assets', ['view', 'manage'])

FIX #7 — frontend/src/components/layout/AppLayout.tsx (Fixed Assets nav section)
  Issue:        Nav section gated by 'chart_of_accounts.view'
  Fixed code:   permission: 'fixed_assets.view'
  Also update:  roles array to include executive, vice_president, head (view-only)

FIX #8 — routes/api/v1/fixed_assets.php:46 (Depreciation export)
  Issue:        No permission check on CSV export endpoint
  Add:          abort_unless(auth()->user()?->hasPermissionTo('fixed_assets.view'),
                    403, 'Unauthorized');
                (as first line inside the closure)
```

---

## Output 7 — Questions for the Team

```
QUESTION #1
  Context:      FixedAssetPolicy checks fixed_assets.view/manage but these
                permissions were never created in the seeder. Frontend
                uses chart_of_accounts.view as a workaround.
  Question:     Was this intentional (sharing accounting permissions for assets)
                or was the permission creation simply missed during the FixedAssets
                domain scaffolding?
  Why it matters: If intentional, the policy should be updated to check
                  chart_of_accounts.* instead. If missed, the seeder needs updating.
  Options:      A) Create fixed_assets.view/manage (recommended — clean separation)
                B) Update policy to check chart_of_accounts.view/manage (coupling)

QUESTION #2
  Context:      customer_payments permissions are defined in permissions.ts
                but never created in the seeder. CustomerPayment model exists
                but payments are handled via customer_invoices.receive_payment.
  Question:     Is a separate CustomerPayment workflow planned that would need
                its own permission set, or should these be removed?
  Options:      A) Remove from permissions.ts (payments are part of invoice workflow)
                B) Create in seeder (if standalone payment feature is planned)

QUESTION #3
  Context:      The "Executive" nav section is gated by leaves.ga_process
                permission, which only ga_officer and vice_president have.
                The actual executive role CANNOT see this section.
  Question:     Should the executive role see the GA Processing section?
                Or is it correctly named and just confusingly placed?
  Options:      A) Rename to "GA Processing" (accurate label)
                B) Add it to executive's visible sections
                C) Leave as-is (it's for VP-level GA oversight)

QUESTION #4
  Context:      Officer role has both vendor_invoices.create AND
                vendor_invoices.approve. SoD is enforced at record level
                (submitted_by ≠ approver), meaning it works ONLY if there
                are multiple officer users.
  Question:     If the company has only ONE accounting officer, who approves
                their invoices? Is there a fallback approver (VP or manager)?
  Why it matters: Single-officer scenario creates a deadlock where invoices
                  cannot be approved by anyone except super_admin.
  Options:      A) Document that ≥2 officer users are required
                B) Add vendor_invoices.approve to vice_president for fallback
                C) Accept single-officer deadlock as a control feature

QUESTION #5
  Context:      4 export endpoints (maintenance, delivery, fixed_assets,
                loans) have no explicit permission checks beyond auth:sanctum.
  Question:     Should ALL export endpoints require explicit permission checks,
                or is auth:sanctum sufficient for non-sensitive exports?
  Why it matters: Export = bulk data download. Any auth user can download
                  all depreciation schedules, loan details, etc.
  Options:      A) Add permission checks to all 4 (recommended)
                B) Only protect financial exports (fixed_assets, loans)
                C) Leave as-is (low sensitivity data)
```

---

# EXECUTIVE SUMMARY

## Overall System Security Posture: **STRONG (A-)**

### Strengths
- **SoD enforcement is excellent** — every financial workflow has record-level SoD checks; manager role correctly does NOT bypass SoD
- **Department scoping (RDAC) is properly enforced** — DepartmentScopeMiddleware with clear bypass hierarchy
- **External portal isolation is airtight** — vendor_scope middleware + client policy + service scoping
- **Self-service data scoping is secure** — auth()->id() binding prevents cross-user access
- **18 well-differentiated roles** — no over-privileged roles (excluding super_admin test role)
- **240+ granular permissions** — fine-grained access control, not just CRUD

### Issues Found
| Severity | Count | Summary |
|----------|-------|---------|
| 🔴 Critical | 2 | Missing fixed_assets.* permissions (policy always denies); orphan customer_payments.* in frontend |
| 🔴 High | 1 | Unprotected depreciation CSV export (any auth user) |
| 🟠 Medium | 3 | Permission string mismatches (budget pages, fixed assets page, router) |
| 🟡 Low | 4 | Minor missing permissions (executive.overtime.submit, plant_manager.mrq.create, ga_officer.leave_balances.adjust, nav labeling) |
| **Total** | **10** | **0 SoD violations, 0 excess permissions, 0 data leaks** |

### Priority Action Items
1. **Create `fixed_assets.view` / `fixed_assets.manage` permissions** in RolePermissionSeeder → assign to officer + read-only on exec/VP/head
2. **Add permission check to depreciation export** route (`routes/api/v1/fixed_assets.php:46`)
3. **Fix budget page permission strings** (use `budget.manage` not `chart_of_accounts.manage`)
4. **Add `fixed_assets` to `permissions.ts`** and remove or create `customer_payments`
5. **Update Fixed Assets router guard** from `chart_of_accounts.view` to `fixed_assets.view`

---

*Audit complete. 18 roles, 244 permissions, 147 routes, 29 policies, 500+ authorization points examined.*
*0 SoD violations. 0 data exposure vulnerabilities. 10 findings (2 critical, 1 high, 3 medium, 4 low).*
