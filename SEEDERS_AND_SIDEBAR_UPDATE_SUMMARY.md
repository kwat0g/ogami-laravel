# Seeders and Sidebar Update Summary

This document summarizes the changes made to enhance employee seeders, integrate attendance data, create comprehensive test data for all modules, and reorganize the sidebar navigation.

## Changes Overview

### 1. Enhanced Employee Seeder with Complete Government IDs

**Files Modified:**
- `database/seeders/ConsolidatedEmployeeSeeder.php`
- `database/seeders/SampleDataSeeder.php`

**New File:**
- `database/seeders/Helpers/GovernmentIdHelper.php`

**Key Changes:**
- Created `GovernmentIdHelper` class to generate realistic Philippine government IDs:
  - SSS Numbers (format: XX-XXXXXXX-X)
  - TIN (format: XXX-XXX-XXX-XXX)
  - PhilHealth (format: XX-XXXXXXXXX-X)
  - Pag-IBIG (format: XXXX-XXXX-XXXX)
- Generates both encrypted values (for storage) and SHA-256 hashes (for uniqueness)
- Generates bank account details (account name, number, bank name from major PH banks)
- Updated employee seeders to populate all government ID fields
- All employees now have complete banking and government ID information

### 2. Integrated Attendance Seeding

**File Modified:**
- `database/seeders/DatabaseSeeder.php`

**Key Changes:**
- Added `SampleAttendanceJanFeb2026Seeder` to the main DatabaseSeeder
- Attendance data now seeds automatically on `php artisan db:seed`
- Creates 588 attendance logs for 15 employees (Jan-Feb 2026)

### 3. New Payroll Seeder

**New File:**
- `database/seeders/SamplePayrollJanFeb2026Seeder.php`

**Features:**
- Creates payroll runs for Jan-Feb 2026
- Generates fiscal periods and pay periods
- Creates payroll details with realistic calculations:
  - Basic pay computation
  - Overtime calculations (1.25x rate)
  - Government contributions (SSS, PhilHealth, Pag-IBIG)
  - Withholding tax based on TRAIN law brackets
  - Net pay calculation

### 4. Comprehensive Test Data Seeder

**New File:**
- `database/seeders/ComprehensiveTestDataSeeder.php`

**Modules Covered:**

#### Inventory Module
- Warehouse Locations (4 zones)
- Item Categories (4 categories)
- Item Masters (raw materials, finished goods)

#### Production Module
- Item Masters for RM and FG
- Bill of Materials with components

#### QC Module
- Inspection Templates
- Inspections

#### Maintenance Module
- Equipment (machines)
- PM Schedules
- Maintenance Work Orders

#### Delivery Module
- Vehicles (trucks and vans)

#### Fixed Assets Module
- Asset Categories
- Fixed Assets

### 5. Sidebar Reorganization

**File Modified:**
- `frontend/src/components/layout/AppLayout.tsx`

**New Structure (Ordered by Business Workflow):**

```
1. HR & PAYROLL (People)
   ├── Human Resources
   │   ├── Employee Directory
   │   ├── Attendance
   │   ├── Leave Management
   │   ├── Overtime
   │   ├── Loans
   │   ├── Organization (Departments, Positions, Shifts)
   │   └── Reports
   └── Payroll
       ├── Payroll Runs
       ├── Pay Periods
       └── Approvals

2. FINANCIAL MANAGEMENT (Money)
   ├── Accounting
   ├── Payables (AP)
   ├── Receivables (AR)
   ├── Financial Reports
   └── Budget

3. SUPPLY CHAIN (Materials)
   ├── Procurement
   │   ├── Purchase Requests
   │   ├── RFQs
   │   ├── Purchase Orders
   │   └── Goods Receipts
   └── Inventory

4. PRODUCTION & QUALITY (Operations)
   ├── Production
   ├── Quality Control
   ├── Maintenance
   └── Mold Management

5. SALES & DELIVERY (Fulfillment)
   ├── CRM
   └── Delivery

6. COMPLIANCE
   ├── ISO / IATF
   └── VP Approvals (special access)

7. ADMINISTRATION (System)
   └── Users, Settings, Audit Logs, etc.
```

**SoD (Segregation of Duties) Guards:**
- Each section has `departments` array for access control
- Role-based filtering with `roles` array
- Permission-based visibility for child items
- Department codes: HR, ACCTG, PURCH, PROD, QC, MAINT, WH, SALES, IT, EXEC, etc.

## Usage

### Fresh Database Seed (Complete)
```bash
php artisan migrate:fresh --seed
```

This will now include:
1. All reference tables (salary grades, leave types, etc.)
2. RBAC roles and permissions
3. Departments and positions
4. 25+ employees with complete government IDs and bank details
5. Attendance data for Jan-Feb 2026 (588 records)
6. Payroll data for Jan-Feb 2026
7. Inventory, Production, QC, Maintenance, and Delivery test data
8. Leave balances for all employees

### Individual Seeders
```bash
# Just attendance
php artisan db:seed --class=SampleAttendanceJanFeb2026Seeder

# Just payroll
php artisan db:seed --class=SamplePayrollJanFeb2026Seeder

# Just module test data
php artisan db:seed --class=ComprehensiveTestDataSeeder

# Just government IDs for existing employees
php artisan tinker
>>> $employees = App\Domains\HR\Models\Employee::all();
>>> foreach ($employees as $e) { \Database\Seeders\Helpers\GovernmentIdHelper::assignToEmployee($e); $e->save(); }
```

## Test Accounts

All test accounts are created with passwords based on role:
- Executive: `Executive@12345!`
- Vice President: `VicePresident@1!`
- Manager: `Manager@12345!`
- Head: `Head@123456789!`
- Officer: `Officer@12345!`
- Staff: `Staff@123456789!`
- Admin: `Admin@12345!`

See `storage/app/test-credentials.md` after seeding for full list.

## Database Seeder Order

```
1. Configuration Tables (tax brackets, contributions, holidays)
2. RBAC (roles, permissions, modules)
3. HR Reference (salary grades, leave types, shifts)
4. Accounting Reference (chart of accounts)
5. Organizational Structure (departments, positions, fiscal periods)
6. Department Permissions
7. Employees (with gov IDs and bank details)
8. Leave Balances
9. Attendance Data
10. Payroll Data
11. Module Test Data (Inventory, Production, QC, Maintenance, Delivery)
12. Fleet
```

## Security Notes

- Government IDs are encrypted using Laravel's `encrypt()` function
- SHA-256 hashes stored for uniqueness checks
- Bank account details stored in plain text (as per business requirement)
- SoD enforced at sidebar level via department and role checks
