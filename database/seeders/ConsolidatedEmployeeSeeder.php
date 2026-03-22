<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Database\Seeders\Helpers\GovernmentIdHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Consolidated Employee and User Account Seeder
 * 
 * This seeder creates employees and their corresponding user accounts
 * with proper role assignments for all departments.
 * 
 * Hierarchy per department:
 * - Executive (for applicable depts)
 * - Manager  
 * - Officer
 * - Head
 * - Staff
 */
class ConsolidatedEmployeeSeeder extends Seeder
{
    private const DEFAULT_PASSWORDS = [
        'executive' => 'Executive@12345!',
        'vice_president' => 'VicePresident@1!',
        'manager' => 'Manager@12345!',
        'head' => 'Head@123456789!',
        'officer' => 'Officer@12345!',
        'staff' => 'Staff@123456789!',
    ];

    public function run(): void
    {
        $this->command->info('Starting Consolidated Employee Seeding...');

        // Create employees and users for each department
        $this->seedExecutiveManagement();
        $this->seedProductionDepartment();
        $this->seedQCDepartment();
        $this->seedWarehouseDepartment();
        $this->seedHRDepartment();
        $this->seedAccountingDepartment();
        $this->seedProcurementDepartment();
        $this->seedSalesDepartment();
        $this->seedMaintenanceDepartment();
        $this->seedITDepartment();
        $this->seedAdminUsers();

        $this->command->info('✓ Consolidated Employee Seeding Complete!');
        $this->displaySummary();
    }

    private function seedExecutiveManagement(): void
    {
        $this->command->info('  → Seeding Executive Management...');

        $executives = [
            [
                'code' => 'EMP-EXE-001',
                'first_name' => 'Roberto',
                'last_name' => 'Ogami',
                'email' => 'chairman@ogamierp.local',
                'position' => 'Chairman',
                'role' => 'executive',
            ],
            [
                'code' => 'EMP-EXE-002',
                'first_name' => 'Eduardo',
                'last_name' => 'Ogami',
                'email' => 'president@ogamierp.local',
                'position' => 'President',
                'role' => 'executive',
            ],
            [
                'code' => 'EMP-EXE-003',
                'first_name' => 'Lorenzo',
                'last_name' => 'Ogami',
                'email' => 'vp@ogamierp.local',
                'position' => 'Vice President',
                'role' => 'vice_president',
            ],
        ];

        // Executives are not assigned to specific departments
        // They have cross-department access
        foreach ($executives as $exec) {
            // Get first department as primary (they have access to all)
            $deptId = DB::table('departments')->first()->id ?? 1;
            $this->createExecutiveUser($exec);
        }
    }

    private function seedProductionDepartment(): void
    {
        $this->command->info('  → Seeding Production Department...');

        $employees = [
            [
                'code' => 'EMP-PROD-001',
                'first_name' => 'Renaldo',
                'last_name' => 'Mendoza',
                'email' => 'prod.manager@ogamierp.local',
                'position' => 'Production Manager',
                'role' => 'manager',
            ],
            [
                'code' => 'EMP-PROD-002',
                'first_name' => 'Danilo',
                'last_name' => 'Espiritu',
                'email' => 'production.head@ogamierp.local',
                'position' => 'Production Head',
                'role' => 'head',
            ],
            [
                'code' => 'EMP-PROD-003',
                'first_name' => 'Eliza',
                'last_name' => 'Navarro',
                'email' => 'processing.head@ogamierp.local',
                'position' => 'Processing Head',
                'role' => 'head',
            ],
            [
                'code' => 'EMP-PROD-004',
                'first_name' => 'Pedro',
                'last_name' => 'Dela Cruz',
                'email' => 'prod.staff@ogamierp.local',
                'position' => 'Production Operator',
                'role' => 'staff',
            ],
        ];

        foreach ($employees as $emp) {
            $this->createEmployeeWithUser($emp, 'PROD', 'PROD-001');
        }
    }

    private function seedQCDepartment(): void
    {
        $this->command->info('  → Seeding QC Department...');

        $employees = [
            [
                'code' => 'EMP-QC-001',
                'first_name' => 'Josephine',
                'last_name' => 'Villanueva',
                'email' => 'qc.manager@ogamierp.local',
                'position' => 'QC/QA Manager',
                'role' => 'manager',
            ],
            [
                'code' => 'EMP-QC-002',
                'first_name' => 'Rhodora',
                'last_name' => 'Salazar',
                'email' => 'qcqa.head@ogamierp.local',
                'position' => 'QC/QA Head',
                'role' => 'head',
            ],
            [
                'code' => 'EMP-QC-003',
                'first_name' => 'Maricel',
                'last_name' => 'Bautista',
                'email' => 'qc.officer@ogamierp.local',
                'position' => 'QC Inspector',
                'role' => 'officer',
            ],
        ];

        foreach ($employees as $emp) {
            $this->createEmployeeWithUser($emp, 'QC', 'QC-001');
        }
    }

    private function seedWarehouseDepartment(): void
    {
        $this->command->info('  → Seeding Warehouse Department...');

        $employees = [
            [
                'code' => 'EMP-WH-001',
                'first_name' => 'Ernesto',
                'last_name' => 'Bautista',
                'email' => 'warehouse.head@ogamierp.local',
                'position' => 'Warehouse Head',
                'role' => 'head',
            ],
            [
                'code' => 'EMP-WH-002',
                'first_name' => 'Manuel',
                'last_name' => 'Garcia',
                'email' => 'wh.staff@ogamierp.local',
                'position' => 'Warehouse Staff',
                'role' => 'staff',
            ],
        ];

        foreach ($employees as $emp) {
            $this->createEmployeeWithUser($emp, 'WH', 'WH-001');
        }
    }

    private function seedHRDepartment(): void
    {
        $this->command->info('  → Seeding HR Department...');

        $employees = [
            [
                'code' => 'EMP-HR-001',
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'email' => 'hr.manager@ogamierp.local',
                'position' => 'HR Manager',
                'role' => 'manager',
            ],
            [
                'code' => 'EMP-HR-002',
                'first_name' => 'Carmen',
                'last_name' => 'Reyes',
                'email' => 'hr.head@ogamierp.local',
                'position' => 'HR Head',
                'role' => 'head',
            ],
            [
                'code' => 'EMP-HR-003',
                'first_name' => 'Jennifer',
                'last_name' => 'Lim',
                'email' => 'hr.officer@ogamierp.local',
                'position' => 'HR Officer',
                'role' => 'officer',
            ],
        ];

        foreach ($employees as $emp) {
            $this->createEmployeeWithUser($emp, 'HR', 'HR-001');
        }
    }

    private function seedAccountingDepartment(): void
    {
        $this->command->info('  → Seeding Accounting Department...');

        $employees = [
            [
                'code' => 'EMP-ACCT-001',
                'first_name' => 'Antonio',
                'last_name' => 'Garcia',
                'email' => 'acctg.manager@ogamierp.local',
                'position' => 'Accounting Manager',
                'role' => 'manager',
            ],
            [
                'code' => 'EMP-ACCT-002',
                'first_name' => 'Luis',
                'last_name' => 'Tan',
                'email' => 'accounting@ogamierp.local',
                'position' => 'Accounting Officer',
                'role' => 'officer',
            ],
            [
                'code' => 'EMP-ACCT-003',
                'first_name' => 'Patricia',
                'last_name' => 'Wong',
                'email' => 'acctg.head@ogamierp.local',
                'position' => 'Accounting Head',
                'role' => 'head',
            ],
        ];

        foreach ($employees as $emp) {
            $this->createEmployeeWithUser($emp, 'ACCTG', 'ACCTG-001');
        }
    }

    private function seedProcurementDepartment(): void
    {
        $this->command->info('  → Seeding Procurement & Purchasing Department...');

        $employees = [
            [
                'code' => 'EMP-PURCH-001',
                'first_name' => 'Marlon',
                'last_name' => 'Torres',
                'email' => 'purchasing.officer@ogamierp.local',
                'position' => 'Purchasing Officer',
                'role' => 'officer',
            ],
            [
                'code' => 'EMP-PURCH-002',
                'first_name' => 'Cristina',
                'last_name' => 'Aquino',
                'email' => 'impex.officer@ogamierp.local',
                'position' => 'ImpEx Officer',
                'role' => 'officer',
            ],
        ];

        foreach ($employees as $emp) {
            $this->createEmployeeWithUser($emp, 'PURCH', 'PURCH-001');
        }
    }

    private function seedSalesDepartment(): void
    {
        $this->command->info('  → Seeding Sales Department...');

        $employees = [
            [
                'code' => 'EMP-SALES-001',
                'first_name' => 'Michelle',
                'last_name' => 'Gomez',
                'email' => 'sales.manager@ogamierp.local',
                'position' => 'Sales Manager',
                'role' => 'manager',
            ],
            [
                'code' => 'EMP-SALES-002',
                'first_name' => 'Richard',
                'last_name' => 'Chua',
                'email' => 'sales.officer@ogamierp.local',
                'position' => 'Sales Officer',
                'role' => 'officer',
            ],
        ];

        foreach ($employees as $emp) {
            $this->createEmployeeWithUser($emp, 'SALES', 'SALES-001');
        }
    }

    private function seedMaintenanceDepartment(): void
    {
        $this->command->info('  → Seeding Maintenance Department...');

        $employees = [
            [
                'code' => 'EMP-MAINT-001',
                'first_name' => 'Armando',
                'last_name' => 'Dela Torre',
                'email' => 'maintenance.head@ogamierp.local',
                'position' => 'Maintenance Head',
                'role' => 'head',
            ],
            [
                'code' => 'EMP-MAINT-002',
                'first_name' => 'Fernando',
                'last_name' => 'Cruz',
                'email' => 'maintenance.staff@ogamierp.local',
                'position' => 'Maintenance Technician',
                'role' => 'staff',
            ],
        ];

        foreach ($employees as $emp) {
            $this->createEmployeeWithUser($emp, 'MAINT', 'MAINT-001');
        }
    }

    private function seedITDepartment(): void
    {
        $this->command->info('  → Seeding IT Department...');

        $employee = [
            'code' => 'EMP-IT-001',
            'first_name' => 'Kevin',
            'last_name' => 'Lim',
            'email' => 'it.admin@ogamierp.local',
            'position' => 'IT Administrator',
            'role' => 'manager',
        ];

        $this->createEmployeeWithUser($employee, 'IT', 'IT-001');
    }

    private function seedAdminUsers(): void
    {
        $this->command->info('  → Seeding System Admin...');

        // Create admin user without employee record
        $admin = User::firstOrCreate(
            ['email' => 'admin@ogamierp.local'],
            [
                'name' => 'System Administrator',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]
        );

        $admin->password = 'Admin@12345!';
        $admin->save();
        $admin->syncRoles(['admin']);

        $this->command->info("    ✓ Created admin user: admin@ogamierp.local");
    }

    private function createExecutiveUser(array $data): void
    {
        // Executives don't have employee records - they are board-level
        $password = self::DEFAULT_PASSWORDS[$data['role']] ?? 'Executive@12345!';
        
        // Check if user exists first
        $user = User::where('email', $data['email'])->first();
        
        if (!$user) {
            // Create new user with password
            $user = User::create([
                'name' => "{$data['first_name']} {$data['last_name']} ({$data['position']})",
                'email' => $data['email'],
                'password' => $password,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]);
        } else {
            // Update existing user's password
            $user->password = $password;
            $user->save();
        }
        
        // Reset any lockouts
        $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        // Assign role
        $user->syncRoles([$data['role']]);

        $this->command->info("    ✓ Created executive: {$data['email']} ({$data['position']})");
    }

    private function createEmployeeWithUser(array $data, string $deptCode, ?string $positionCode): void
    {
        // Get department ID
        $deptId = DB::table('departments')->where('code', $deptCode)->value('id');
        
        if (!$deptId) {
            $this->command->warn("    ⚠ Department {$deptCode} not found, skipping {$data['email']}");
            return;
        }

        // Get position ID if provided
        $positionId = null;
        if ($positionCode) {
            $positionId = DB::table('positions')->where('code', $positionCode)->value('id');
        }

        // Get salary grade (default to SG-01 for staff, higher for managers)
        $sgCode = match($data['role']) {
            'executive', 'vice_president' => 'SG-15',
            'manager' => 'SG-12',
            'head' => 'SG-09',
            'officer' => 'SG-07',
            'staff' => 'SG-05',
            default => 'SG-05',
        };
        $sgId = DB::table('salary_grades')->where('code', $sgCode)->value('id');

        // Generate government IDs
        $govIds = GovernmentIdHelper::generateCompleteGovIds();
        
        // Generate bank details
        $bankDetails = GovernmentIdHelper::generateBankDetails($data['first_name'], $data['last_name']);
        
        // Create or update employee with all required fields
        $employeeData = [
            'ulid' => (string) Str::ulid(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'department_id' => $deptId,
            'position_id' => $positionId,
            'salary_grade_id' => $sgId,
            'date_hired' => now()->subYears(rand(1, 10)),
            'date_of_birth' => now()->subYears(rand(25, 50))->subDays(rand(1, 365)),
            'gender' => 'male',
            'civil_status' => 'SINGLE',
            'citizenship' => 'Filipino',
            'present_address' => 'Test Address, Quezon City',
            'permanent_address' => 'Test Address, Quezon City',
            'bir_status' => 'S',
            'employment_type' => 'regular',
            'employment_status' => 'active',
            'pay_basis' => 'monthly',
            'is_active' => true,
            'onboarding_status' => 'active',
            'basic_monthly_rate' => 250000, // 25,000 PHP in centavos
            'personal_email' => strtolower($data['first_name']).'.'.strtolower($data['last_name']).'@email.com',
            'personal_phone' => '0917'.rand(1000000, 9999999),
            // Government IDs (encrypted + hash)
            'sss_no_encrypted' => $govIds['sss_no_encrypted'],
            'sss_no_hash' => $govIds['sss_no_hash'],
            'tin_encrypted' => $govIds['tin_encrypted'],
            'tin_hash' => $govIds['tin_hash'],
            'philhealth_no_encrypted' => $govIds['philhealth_no_encrypted'],
            'philhealth_no_hash' => $govIds['philhealth_no_hash'],
            'pagibig_no_encrypted' => $govIds['pagibig_no_encrypted'],
            'pagibig_no_hash' => $govIds['pagibig_no_hash'],
            // Bank details
            'bank_name' => $bankDetails['bank_name'],
            'bank_account_no' => $bankDetails['bank_account_number'],
            'bank_account_name' => $bankDetails['bank_account_name'],
            'created_at' => now(),
            'updated_at' => now(),
        ];
        
        // Try to insert, if exists update
        $existing = DB::table('employees')->where('employee_code', $data['code'])->first();
        if ($existing) {
            DB::table('employees')->where('id', $existing->id)->update($employeeData);
            $employeeId = $existing->id;
        } else {
            $employeeData['employee_code'] = $data['code'];
            $employeeId = DB::table('employees')->insertGetId($employeeData);
        }

        // Create user account with proper password
        $password = self::DEFAULT_PASSWORDS[$data['role']] ?? 'Password@123!';
        
        // Check if user exists first
        $user = User::where('email', $data['email'])->first();
        
        if (!$user) {
            // Create new user with password
            $user = User::create([
                'name' => "{$data['first_name']} {$data['last_name']} ({$data['position']})",
                'email' => $data['email'],
                'password' => $password, // Password will be hashed by model mutator
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]);
        } else {
            // Update existing user's password
            $user->password = $password;
            $user->save();
        }
        
        // Update employee with user_id
        DB::table('employees')
            ->where('id', $employeeId)
            ->update(['user_id' => $user->id]);

        // Update user with department_id
        $user->update(['department_id' => $deptId]);
        
        // Sync department access (pivot table) - required by ModuleAccessMiddleware
        $user->departments()->sync([$deptId => ['is_primary' => true]]);

        // Reset any lockouts
        $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        // Assign role
        $user->syncRoles([$data['role']]);

        $this->command->info("    ✓ Created: {$data['email']} ({$data['position']})");
    }

    private function displaySummary(): void
    {
        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════════════════');
        $this->command->info('              TEST ACCOUNTS READY FOR TESTING                     ');
        $this->command->info('═══════════════════════════════════════════════════════════════════');
        
        // Get all users created by this seeder
        $emails = [
            'chairman@ogamierp.local',
            'president@ogamierp.local',
            'vp@ogamierp.local',
            'prod.manager@ogamierp.local',
            'production.head@ogamierp.local',
            'processing.head@ogamierp.local',
            'prod.staff@ogamierp.local',
            'qc.manager@ogamierp.local',
            'qcqa.head@ogamierp.local',
            'qc.officer@ogamierp.local',
            'warehouse.head@ogamierp.local',
            'wh.staff@ogamierp.local',
            'hr.manager@ogamierp.local',
            'hr.head@ogamierp.local',
            'hr.officer@ogamierp.local',
            'acctg.manager@ogamierp.local',
            'accounting@ogamierp.local',
            'acctg.head@ogamierp.local',
            'sales.manager@ogamierp.local',
            'sales.officer@ogamierp.local',
            'purchasing.officer@ogamierp.local',
            'impex.officer@ogamierp.local',
            'maintenance.head@ogamierp.local',
            'maintenance.staff@ogamierp.local',
            'it.admin@ogamierp.local',
            'admin@ogamierp.local',
        ];
        
        $users = User::whereIn('email', $emails)
            ->with('roles')
            ->orderBy('email')
            ->get();
        
        // Group by role for better display
        $byRole = [];
        foreach ($users as $user) {
            $roleName = $user->roles->first()->name ?? 'no role';
            $password = match($roleName) {
                'executive' => self::DEFAULT_PASSWORDS['executive'],
                'vice_president' => self::DEFAULT_PASSWORDS['vice_president'],
                'manager' => self::DEFAULT_PASSWORDS['manager'],
                'head' => self::DEFAULT_PASSWORDS['head'],
                'officer' => self::DEFAULT_PASSWORDS['officer'],
                'staff' => self::DEFAULT_PASSWORDS['staff'],
                'admin' => 'Admin@12345!',
                default => 'Unknown',
            };
            
            $byRole[$roleName][] = [
                'email' => $user->email,
                'name' => $user->name,
                'password' => $password,
            ];
        }
        
        // Build content for file
        $content = "# Ogami ERP - Test Account Credentials\n\n";
        $content .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";
        $content .= "## Login URL\n";
        $content .= "http://localhost:5173/login\n\n";
        $content .= "## Credentials by Role\n\n";
        
        // Display by role and build file content
        foreach ($byRole as $role => $accounts) {
            $this->command->newLine();
            $this->command->info(strtoupper($role) . ' (' . count($accounts) . ' accounts)');
            $this->command->info('───────────────────────────────────────────────────────────────────');
            
            $content .= "### " . strtoupper($role) . " (" . count($accounts) . " accounts)\n\n";
            $content .= "| Email | Password |\n";
            $content .= "|-------|----------|\n";
            
            foreach ($accounts as $account) {
                $this->command->info(sprintf(
                    '  %-38s │ %s',
                    $account['email'],
                    $account['password']
                ));
                $content .= "| {$account['email']} | {$account['password']} |\n";
            }
            $content .= "\n";
        }
        
        // Add quick reference section
        $content .= "## Quick Reference\n\n";
        $content .= "### Passwords by Role\n\n";
        $content .= "| Role | Password |\n";
        $content .= "|------|----------|\n";
        $content .= "| Executive | " . self::DEFAULT_PASSWORDS['executive'] . " |\n";
        $content .= "| VP | " . self::DEFAULT_PASSWORDS['vice_president'] . " |\n";
        $content .= "| Manager | " . self::DEFAULT_PASSWORDS['manager'] . " |\n";
        $content .= "| Head | " . self::DEFAULT_PASSWORDS['head'] . " |\n";
        $content .= "| Officer | " . self::DEFAULT_PASSWORDS['officer'] . " |\n";
        $content .= "| Staff | " . self::DEFAULT_PASSWORDS['staff'] . " |\n";
        $content .= "| Admin | Admin@12345! |\n";
        
        // Save to file
        $filePath = storage_path('app/test-credentials.md');
        file_put_contents($filePath, $content);
        
        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════════════════');
        $this->command->info('  ✅ All accounts are ready for testing!');
        $this->command->info('  ✅ Credentials saved to: storage/app/test-credentials.md');
        $this->command->info('  🌐 Login at: http://localhost:5173/login');
        $this->command->info('═══════════════════════════════════════════════════════════════════');
    }
}
