# Ogami ERP - Test Accounts Quick Reference

## 🔑 Most Used Accounts

| Purpose | Email | Password | Employee Code |
|---------|-------|----------|---------------|
| **HR Manager** | `hr.manager@ogamierp.local` | `Manager@Test1234!` | EMP-HR-001 |
| **HR Head** | `hr.head@ogamierp.local` | `Head@Test1234!` | EMP-HR-003 |
| **Accounting Manager** | `acctg.manager@ogamierp.local` | `Manager@12345!` | EMP-ACCT-001 |
| **Accounting Officer** | `acctg.officer@ogamierp.local` | `Officer@Test1234!` | EMP-ACCT-002 |
| **VP Approvals** | `vp@ogamierp.local` | `Vice_president@Test1234!` | EMP-EXEC-002 |
| **Production Manager** | `prod.manager@ogamierp.local` | `Production_manager@Test1234!` | EMP-PROD-001 |
| **QC Manager** | `qc.manager@ogamierp.local` | `Qc_manager@Test1234!` | EMP-QC-001 |

## 📊 All Accounts by Department

### HR Department
| Role | Email | Password | Employee Code |
|------|-------|----------|---------------|
| HR Manager | `hr.manager@ogamierp.local` | `Manager@Test1234!` | EMP-HR-001 |
| GA Officer | `ga.officer@ogamierp.local` | `Ga_officer@Test1234!` | EMP-HR-002 |
| HR Head | `hr.head@ogamierp.local` | `Head@Test1234!` | EMP-HR-003 |
| HR Staff | `hr.staff@ogamierp.local` | `Staff@Test1234!` | EMP-HR-004 |

### ACCTG Department
| Role | Email | Password | Employee Code |
|------|-------|----------|---------------|
| Accounting Manager | `acctg.manager@ogamierp.local` | `Manager@12345!` | EMP-ACCT-001 |
| Accounting Officer | `acctg.officer@ogamierp.local` | `Officer@Test1234!` | EMP-ACCT-002 |
| Accounting Head | `acctg.head@ogamierp.local` | `Head@Test1234!` | EMP-ACCT-003 |
| Accounting Staff | `acctg.staff@ogamierp.local` | `Staff@Test1234!` | EMP-ACCT-004 |

### PROD Department
| Role | Email | Password | Employee Code |
|------|-------|----------|---------------|
| Production Manager | `prod.manager@ogamierp.local` | `Production_manager@Test1234!` | EMP-PROD-001 |
| Production Head | `prod.head@ogamierp.local` | `Head@Test1234!` | EMP-PROD-002 |
| Production Staff | `prod.staff@ogamierp.local` | `Staff@Test1234!` | EMP-PROD-003 |

### QC Department
| Role | Email | Password | Employee Code |
|------|-------|----------|---------------|
| QC Manager | `qc.manager@ogamierp.local` | `Qc_manager@Test1234!` | EMP-QC-001 |
| QC Head | `qc.head@ogamierp.local` | `Head@Test1234!` | EMP-QC-002 |
| QC Staff | `qc.staff@ogamierp.local` | `Staff@Test1234!` | EMP-QC-003 |

### MOLD Department
| Role | Email | Password | Employee Code |
|------|-------|----------|---------------|
| Mold Manager | `mold.manager@ogamierp.local` | `Mold_manager@Test1234!` | EMP-MOLD-001 |
| Mold Head | `mold.head@ogamierp.local` | `Head@Test1234!` | EMP-MOLD-002 |
| Mold Staff | `mold.staff@ogamierp.local` | `Staff@Test1234!` | EMP-MOLD-003 |

### PLANT Department
| Role | Email | Password | Employee Code |
|------|-------|----------|---------------|
| Plant Manager | `plant.manager@ogamierp.local` | `Plant_manager@Test1234!` | EMP-PLANT-001 |
| Plant Head | `plant.head@ogamierp.local` | `Head@Test1234!` | EMP-PLANT-002 |

### SALES Department
| Role | Email | Password | Employee Code |
|------|-------|----------|---------------|
| CRM Manager | `crm.manager@ogamierp.local` | `CrmManager@12345!` | EMP-SALES-001 |
| Sales Staff | `sales.staff@ogamierp.local` | `Staff@Test1234!` | EMP-SALES-002 |

### IT Department
| Role | Email | Password | Employee Code |
|------|-------|----------|---------------|
| IT Admin | `it.admin@ogamierp.local` | `Manager@12345!` | EMP-IT-001 |

### EXEC Department
| Role | Email | Password | Employee Code |
|------|-------|----------|---------------|
| Executive | `executive@ogamierp.local` | `Executive@Test1234!` | EMP-EXEC-001 |
| Vice President | `vp@ogamierp.local` | `Vice_president@Test1234!` | EMP-EXEC-002 |

## 🌐 External Portals

| Portal | Email | Password |
|--------|-------|----------|
| Vendor Portal | `vendor@ogamierp.local` | `Vendor@Test1234!` |
| Client Portal | `client@ogamierp.local` | `Client@Test1234!` |

## 🔧 Password Pattern

Test accounts follow the pattern: **`{RoleName}@Test1234!`**

The role name has the **first letter capitalized**, rest lowercase:

| Role | Password |
|------|----------|
| manager | `Manager@Test1234!` |
| officer | `Officer@Test1234!` |
| vice_president | `Vice_president@Test1234!` |
| plant_manager | `Plant_manager@Test1234!` |
| production_manager | `Production_manager@Test1234!` |
| qc_manager | `Qc_manager@Test1234!` |
| mold_manager | `Mold_manager@Test1234!` |
| ga_officer | `Ga_officer@Test1234!` |
| head | `Head@Test1234!` |
| staff | `Staff@Test1234!` |
| vendor | `Vendor@Test1234!` |
| client | `Client@Test1234!` |
| executive | `Executive@Test1234!` |

**Special passwords (non-standard):**
- `acctg.manager@ogamierp.local` → `Manager@12345!`
- `crm.manager@ogamierp.local` → `CrmManager@12345!`
- `it.admin@ogamierp.local` → `Manager@12345!`

## 🧪 Testing Priority Order

1. **HR Manager** (`hr.manager@ogamierp.local`) - Test HR → Attendance → Leave → Payroll flow
2. **Accounting Officer** (`acctg.officer@ogamierp.local`) - Test GL, AP, AR, Tax modules
3. **VP** (`vp@ogamierp.local`) - Test approvals dashboard
4. **Production Manager** (`prod.manager@ogamierp.local`) - Test Production → Inventory flow
5. **Staff** (`hr.staff@ogamierp.local`) - Test self-service features

## 🚨 Common Issues

| Issue | Solution |
|-------|----------|
| Can't login | Run `php artisan db:seed --class=ComprehensiveTestAccountsSeeder` |
| Missing permissions | Run `php artisan db:seed --class=RolePermissionSeeder` |
| Account locked | Check `users` table for `is_active` flag |
| Wrong department | Check `user_department_access` table |
| Missing employee link | Check `employees.user_id` is set |

## 📊 Total Accounts Summary

- **23 Employee-linked accounts** across 9 departments
- **2 External portal accounts** (vendor, client)
- **All accounts** use consistent password pattern
- **All employees** linked to user accounts for payroll/leave testing
