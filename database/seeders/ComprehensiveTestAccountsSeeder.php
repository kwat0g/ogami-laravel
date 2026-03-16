<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comprehensive Test Accounts Seeder
 * 
 * Creates employee records linked to user accounts for all major departments.
 * Each department gets: Manager, Officer, Head, and Staff (where applicable)
 * 
 * Password Pattern: {RoleName}@Test1234! (first letter capitalized)
 * 
 * Accounts created:
 * - HR: Manager + Officer + Head + Staff
 * - ACCTG: Manager + Officer + Head + Staff
 * - PROD: Manager + Head + Staff
 * - QC: Manager + Head + Staff
 * - MOLD: Manager + Head + Staff
 * - PLANT: Manager + Head
 * - SALES: Manager + Staff
 * - IT: Admin
 * - EXEC: VP
 */
class ComprehensiveTestAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('  COMPREHENSIVE TEST ACCOUNTS SEEDER');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        
        $this->seedHREmployees();
        $this->seedAccountingEmployees();
        $this->seedProductionEmployees();
        $this->seedQCEmployees();
        $this->seedMoldEmployees();
        $this->seedPlantEmployees();
        $this->seedSalesEmployees();
        $this->seedITEmployees();
        $this->seedExecutiveEmployees();
        
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('  ALL TEST ACCOUNTS SUMMARY');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->printAccountSummary();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HR DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedHREmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ HR Department ───────────────────────────────────────────────');
        
        $employees = [
            [
                'code' => 'EMP-HR-001',
                'first_name' => 'Maria',
                'middle_name' => 'Reyes',
                'last_name' => 'Santos',
                'email' => 'hr.manager@ogamierp.local',
                'role' => 'manager',
                'position' => 'HR-MGR',
                'salary' => 4500000,
                'user_name' => 'Maria Santos (HR Manager)',
            ],
            [
                'code' => 'EMP-HR-002',
                'first_name' => 'Grace',
                'middle_name' => 'Mendoza',
                'last_name' => 'Torres',
                'email' => 'ga.officer@ogamierp.local',
                'role' => 'ga_officer',
                'position' => 'GA-OFF',
                'salary' => 3500000,
                'user_name' => 'Grace Torres (GA Officer)',
            ],
            [
                'code' => 'EMP-HR-003',
                'first_name' => 'Ricardo',
                'middle_name' => 'Bautista',
                'last_name' => 'Cruz',
                'email' => 'hr.head@ogamierp.local',
                'role' => 'head',
                'position' => 'HR-SUP',
                'salary' => 2800000,
                'user_name' => 'Ricardo Cruz (HR Head)',
            ],
            [
                'code' => 'EMP-HR-004',
                'first_name' => 'Juan',
                'middle_name' => 'Dela',
                'last_name' => 'Cruz',
                'email' => 'hr.staff@ogamierp.local',
                'role' => 'staff',
                'position' => 'HR-ASST',
                'salary' => 1800000,
                'user_name' => 'Juan Dela Cruz (HR Staff)',
            ],
        ];
        
        $this->createEmployeesWithUsers($employees, 'HR');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ACCOUNTING DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedAccountingEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ ACCTG Department ────────────────────────────────────────────');
        
        $employees = [
            [
                'code' => 'EMP-ACCT-001',
                'first_name' => 'Amelia',
                'middle_name' => 'Dela Cruz',
                'last_name' => 'Cordero',
                'email' => 'acctg.manager@ogamierp.local',
                'role' => 'officer',
                'position' => 'ACCT-MGR',
                'salary' => 5500000,
                'user_name' => 'Amelia Cordero (Accounting Manager)',
                'password' => 'Manager@12345!',
            ],
            [
                'code' => 'EMP-ACCT-002',
                'first_name' => 'Anna Marie',
                'middle_name' => 'Cruz',
                'last_name' => 'Lim',
                'email' => 'acctg.officer@ogamierp.local',
                'role' => 'officer',
                'position' => 'ACCT-OFF',
                'salary' => 3800000,
                'user_name' => 'Anna Marie Lim (Accounting Officer)',
            ],
            [
                'code' => 'EMP-ACCT-003',
                'first_name' => 'Roberto',
                'middle_name' => 'Santos',
                'last_name' => 'Fernandez',
                'email' => 'acctg.head@ogamierp.local',
                'role' => 'head',
                'position' => 'ACCT-ANL',
                'salary' => 3200000,
                'user_name' => 'Roberto Fernandez (Accounting Head)',
            ],
            [
                'code' => 'EMP-ACCT-004',
                'first_name' => 'Miguel',
                'middle_name' => 'Reyes',
                'last_name' => 'Garcia',
                'email' => 'acctg.staff@ogamierp.local',
                'role' => 'staff',
                'position' => 'ACCT-CLK',
                'salary' => 2000000,
                'user_name' => 'Miguel Garcia (Accounting Staff)',
            ],
        ];
        
        $this->createEmployeesWithUsers($employees, 'ACCTG');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PRODUCTION DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedProductionEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ PROD Department ─────────────────────────────────────────────');
        
        $employees = [
            [
                'code' => 'EMP-PROD-001',
                'first_name' => 'Jose',
                'middle_name' => 'Garcia',
                'last_name' => 'Martinez',
                'email' => 'prod.manager@ogamierp.local',
                'role' => 'production_manager',
                'position' => 'PROD-MGR',
                'salary' => 4800000,
                'user_name' => 'Jose Martinez (Production Manager)',
            ],
            [
                'code' => 'EMP-PROD-002',
                'first_name' => 'Elena',
                'middle_name' => 'Diaz',
                'last_name' => 'Rodriguez',
                'email' => 'prod.head@ogamierp.local',
                'role' => 'head',
                'position' => 'PROD-HEAD',
                'salary' => 3500000,
                'user_name' => 'Elena Rodriguez (Production Head)',
            ],
            [
                'code' => 'EMP-PROD-003',
                'first_name' => 'Carlos',
                'middle_name' => 'Santos',
                'last_name' => 'Reyes',
                'email' => 'prod.staff@ogamierp.local',
                'role' => 'staff',
                'position' => 'PROD-OP',
                'salary' => 2200000,
                'user_name' => 'Carlos Reyes (Production Staff)',
            ],
        ];
        
        $this->createEmployeesWithUsers($employees, 'PROD');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // QC DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedQCEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ QC Department ───────────────────────────────────────────────');
        
        $employees = [
            [
                'code' => 'EMP-QC-001',
                'first_name' => 'Linda',
                'middle_name' => 'Tan',
                'last_name' => 'Wong',
                'email' => 'qc.manager@ogamierp.local',
                'role' => 'qc_manager',
                'position' => 'QC-MGR',
                'salary' => 4600000,
                'user_name' => 'Linda Wong (QC Manager)',
            ],
            [
                'code' => 'EMP-QC-002',
                'first_name' => 'Patricia',
                'middle_name' => 'Lopez',
                'last_name' => 'Gonzalez',
                'email' => 'qc.head@ogamierp.local',
                'role' => 'head',
                'position' => 'QC-HEAD',
                'salary' => 3400000,
                'user_name' => 'Patricia Gonzalez (QC Head)',
            ],
            [
                'code' => 'EMP-QC-003',
                'first_name' => 'Ramon',
                'middle_name' => 'Aquino',
                'last_name' => 'Flores',
                'email' => 'qc.staff@ogamierp.local',
                'role' => 'staff',
                'position' => 'QC-STAFF',
                'salary' => 2100000,
                'user_name' => 'Ramon Flores (QC Staff)',
            ],
        ];
        
        $this->createEmployeesWithUsers($employees, 'QC');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MOLD DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedMoldEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ MOLD Department ─────────────────────────────────────────────');
        
        $employees = [
            [
                'code' => 'EMP-MOLD-001',
                'first_name' => 'Ramon',
                'middle_name' => 'Aquino',
                'last_name' => 'Del Rosario',
                'email' => 'mold.manager@ogamierp.local',
                'role' => 'mold_manager',
                'position' => 'MOLD-MGR',
                'salary' => 4700000,
                'user_name' => 'Ramon Del Rosario (Mold Manager)',
            ],
            [
                'code' => 'EMP-MOLD-002',
                'first_name' => 'Fernando',
                'middle_name' => 'Cruz',
                'last_name' => 'Bautista',
                'email' => 'mold.head@ogamierp.local',
                'role' => 'head',
                'position' => 'MOLD-HEAD',
                'salary' => 3300000,
                'user_name' => 'Fernando Bautista (Mold Head)',
            ],
            [
                'code' => 'EMP-MOLD-003',
                'first_name' => 'Antonio',
                'middle_name' => 'Reyes',
                'last_name' => 'Gomez',
                'email' => 'mold.staff@ogamierp.local',
                'role' => 'staff',
                'position' => 'MOLD-TECH',
                'salary' => 2300000,
                'user_name' => 'Antonio Gomez (Mold Staff)',
            ],
        ];
        
        $this->createEmployeesWithUsers($employees, 'MOLD');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PLANT DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedPlantEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ PLANT Department ────────────────────────────────────────────');
        
        $employees = [
            [
                'code' => 'EMP-PLANT-001',
                'first_name' => 'Carlos',
                'middle_name' => 'Rivera',
                'last_name' => 'Mendoza',
                'email' => 'plant.manager@ogamierp.local',
                'role' => 'plant_manager',
                'position' => 'PLANT-MGR',
                'salary' => 5200000,
                'user_name' => 'Carlos Mendoza (Plant Manager)',
            ],
            [
                'code' => 'EMP-PLANT-002',
                'first_name' => 'Manuel',
                'middle_name' => 'Santos',
                'last_name' => 'Villar',
                'email' => 'plant.head@ogamierp.local',
                'role' => 'head',
                'position' => 'PROD-SUP',
                'salary' => 3600000,
                'user_name' => 'Manuel Villar (Plant Head)',
            ],
        ];
        
        $this->createEmployeesWithUsers($employees, 'PLANT');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SALES DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedSalesEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ SALES Department ────────────────────────────────────────────');
        
        $employees = [
            [
                'code' => 'EMP-SALES-001',
                'first_name' => 'Carrie',
                'middle_name' => 'San Jose',
                'last_name' => 'Macaraig',
                'email' => 'crm.manager@ogamierp.local',
                'role' => 'crm_manager',
                'position' => 'SALES-MGR',
                'salary' => 4000000,
                'user_name' => 'Carrie Macaraig (CRM Manager)',
            ],
            [
                'code' => 'EMP-SALES-002',
                'first_name' => 'Diana',
                'middle_name' => 'Ramos',
                'last_name' => 'Santos',
                'email' => 'sales.staff@ogamierp.local',
                'role' => 'staff',
                'position' => 'SALES-REP',
                'salary' => 2500000,
                'user_name' => 'Diana Santos (Sales Staff)',
            ],
        ];
        
        $this->createEmployeesWithUsers($employees, 'SALES');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // IT DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedITEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ IT Department ───────────────────────────────────────────────');
        
        $employees = [
            [
                'code' => 'EMP-IT-001',
                'first_name' => 'Reynold',
                'middle_name' => 'Tecson',
                'last_name' => 'Santos',
                'email' => 'it.admin@ogamierp.local',
                'role' => 'admin',
                'position' => 'IT-ADMIN',
                'salary' => 4500000,
                'user_name' => 'Reynold Santos (IT Admin)',
                'password' => 'Manager@12345!',
            ],
        ];
        
        $this->createEmployeesWithUsers($employees, 'IT');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // EXECUTIVE DEPARTMENT
    // ═══════════════════════════════════════════════════════════════════════════
    private function seedExecutiveEmployees(): void
    {
        $this->command->info('');
        $this->command->info('─ EXEC Department ─────────────────────────────────────────────');
        
        $employees = [
            [
                'code' => 'EMP-EXEC-001',
                'first_name' => 'Roberto',
                'middle_name' => 'Reyes',
                'last_name' => 'Cruz',
                'email' => 'executive@ogamierp.local',
                'role' => 'executive',
                'position' => 'PRESIDENT',
                'salary' => 10000000,
                'user_name' => 'Roberto Cruz (Executive)',
            ],
            [
                'code' => 'EMP-EXEC-002',
                'first_name' => 'Elena',
                'middle_name' => 'Cruz',
                'last_name' => 'Villanueva',
                'email' => 'vp@ogamierp.local',
                'role' => 'vice_president',
                'position' => 'VP',
                'salary' => 8000000,
                'user_name' => 'Elena Villanueva (VP)',
            ],
        ];
        
        $this->createEmployeesWithUsers($employees, 'EXEC');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ═══════════════════════════════════════════════════════════════════════════
    
    private function createEmployeesWithUsers(array $employees, string $deptCode): void
    {
        $deptId = DB::table('departments')->where('code', $deptCode)->value('id');
        
        if (!$deptId) {
            $this->command->warn("  Department {$deptCode} not found!");
            return;
        }
        
        foreach ($employees as $emp) {
            $posId = DB::table('positions')->where('code', $emp['position'])->value('id');
            $sgId = DB::table('salary_grades')->where('code', 'SG-05')->value('id');
            
            // Create or update employee
            $employee = Employee::firstOrCreate(
                ['employee_code' => $emp['code']],
                [
                    'ulid' => (string) Str::ulid(),
                    'first_name' => $emp['first_name'],
                    'middle_name' => $emp['middle_name'] ?? null,
                    'last_name' => $emp['last_name'],
                    'date_of_birth' => '1985-01-01',
                    'gender' => 'male',
                    'civil_status' => 'SINGLE',
                    'citizenship' => 'Filipino',
                    'present_address' => 'Test Address',
                    'permanent_address' => 'Test Address',
                    'personal_email' => strtolower(str_replace(' ', '.', $emp['first_name'])) . '@email.com',
                    'personal_phone' => '09171234567',
                    'department_id' => $deptId,
                    'position_id' => $posId,
                    'salary_grade_id' => $sgId,
                    'employment_type' => 'regular',
                    'employment_status' => 'active',
                    'pay_basis' => 'monthly',
                    'basic_monthly_rate' => $emp['salary'],
                    'date_hired' => '2020-01-01',
                    'onboarding_status' => 'active',
                    'is_active' => true,
                ]
            );
            
            // Generate password
            $password = $emp['password'] ?? $this->generatePassword($emp['role']);
            
            // Create or update user
            $user = User::firstOrCreate(
                ['email' => $emp['email']],
                [
                    'name' => $emp['user_name'],
                    'password' => $password,
                    'email_verified_at' => now(),
                    'password_changed_at' => now(),
                    'department_id' => $deptId,
                ]
            );
            
            $user->syncRoles([$emp['role']]);
            
            // Link employee to user
            $employee->user_id = $user->id;
            $employee->save();
            
            // Add department access
            DB::table('user_department_access')->insertOrIgnore([
                'user_id' => $user->id,
                'department_id' => $deptId,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->command->info("  ✓ {$emp['code']}: {$emp['email']} / {$password}");
        }
    }
    
    private function generatePassword(string $role): string
    {
        // Capitalize first letter, keep rest lowercase
        $capitalizedRole = ucfirst(strtolower($role));
        return $capitalizedRole . '@Test1234!';
    }
    
    private function printAccountSummary(): void
    {
        $accounts = [
            ['HR', 'hr.manager@ogamierp.local', 'Manager@Test1234!', 'HR Manager', 'EMP-HR-001'],
            ['HR', 'ga.officer@ogamierp.local', 'Ga_officer@Test1234!', 'GA Officer', 'EMP-HR-002'],
            ['HR', 'hr.head@ogamierp.local', 'Head@Test1234!', 'HR Head', 'EMP-HR-003'],
            ['HR', 'hr.staff@ogamierp.local', 'Staff@Test1234!', 'HR Staff', 'EMP-HR-004'],
            ['ACCTG', 'acctg.manager@ogamierp.local', 'Manager@12345!', 'Accounting Manager', 'EMP-ACCT-001'],
            ['ACCTG', 'acctg.officer@ogamierp.local', 'Officer@Test1234!', 'Accounting Officer', 'EMP-ACCT-002'],
            ['ACCTG', 'acctg.head@ogamierp.local', 'Head@Test1234!', 'Accounting Head', 'EMP-ACCT-003'],
            ['ACCTG', 'acctg.staff@ogamierp.local', 'Staff@Test1234!', 'Accounting Staff', 'EMP-ACCT-004'],
            ['PROD', 'prod.manager@ogamierp.local', 'Production_manager@Test1234!', 'Production Manager', 'EMP-PROD-001'],
            ['PROD', 'prod.head@ogamierp.local', 'Head@Test1234!', 'Production Head', 'EMP-PROD-002'],
            ['PROD', 'prod.staff@ogamierp.local', 'Staff@Test1234!', 'Production Staff', 'EMP-PROD-003'],
            ['QC', 'qc.manager@ogamierp.local', 'Qc_manager@Test1234!', 'QC Manager', 'EMP-QC-001'],
            ['QC', 'qc.head@ogamierp.local', 'Head@Test1234!', 'QC Head', 'EMP-QC-002'],
            ['QC', 'qc.staff@ogamierp.local', 'Staff@Test1234!', 'QC Staff', 'EMP-QC-003'],
            ['MOLD', 'mold.manager@ogamierp.local', 'Mold_manager@Test1234!', 'Mold Manager', 'EMP-MOLD-001'],
            ['MOLD', 'mold.head@ogamierp.local', 'Head@Test1234!', 'Mold Head', 'EMP-MOLD-002'],
            ['MOLD', 'mold.staff@ogamierp.local', 'Staff@Test1234!', 'Mold Staff', 'EMP-MOLD-003'],
            ['PLANT', 'plant.manager@ogamierp.local', 'Plant_manager@Test1234!', 'Plant Manager', 'EMP-PLANT-001'],
            ['PLANT', 'plant.head@ogamierp.local', 'Head@Test1234!', 'Plant Head', 'EMP-PLANT-002'],
            ['SALES', 'crm.manager@ogamierp.local', 'CrmManager@12345!', 'CRM Manager', 'EMP-SALES-001'],
            ['SALES', 'sales.staff@ogamierp.local', 'Staff@Test1234!', 'Sales Staff', 'EMP-SALES-002'],
            ['IT', 'it.admin@ogamierp.local', 'Manager@12345!', 'IT Admin', 'EMP-IT-001'],
            ['EXEC', 'executive@ogamierp.local', 'Executive@Test1234!', 'Executive', 'EMP-EXEC-001'],
            ['EXEC', 'vp@ogamierp.local', 'Vice_president@Test1234!', 'VP', 'EMP-EXEC-002'],
        ];
        
        $this->command->info('');
        $this->command->table(
            ['Dept', 'Email', 'Password', 'Role', 'Employee Code'],
            $accounts
        );
        
        $this->command->info('');
        $this->command->info('Total accounts created: ' . count($accounts));
        $this->command->info('');
        $this->command->info('Password Pattern: {RoleName}@Test1234! (first letter capitalized)');
        $this->command->info('Special passwords:');
        $this->command->info('  - acctg.manager: Manager@12345!');
        $this->command->info('  - crm.manager: CrmManager@12345!');
        $this->command->info('  - it.admin: Manager@12345!');
    }
}
