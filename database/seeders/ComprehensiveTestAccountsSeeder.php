<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\HR\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * ComprehensiveTestAccountsSeeder
 *
 * Purpose:
 * - Keep a small, reliable set of employee-linked demo accounts for cross-module demos.
 * - Preserve all employee records while pruning non-essential internal user logins.
 * - Preserve vendor/client/admin accounts (including portal users).
 */
class ComprehensiveTestAccountsSeeder extends Seeder
{
    /**
     * Display order for grouped role output.
     *
     * @var array<int, string>
     */
    private const ROLE_OUTPUT_ORDER = [
        'super_admin',
        'admin',
        'executive',
        'vice_president',
        'manager',
        'head',
        'officer',
        'staff',
        'vendor',
        'client',
        'unassigned',
    ];

    /**
     * @var array<int, array{email: string, password: string, role: string, position: string}>
     */
    private array $newAccounts = [];

    /**
     * @var array<string, string>
     */
    private array $credentialMap = [];

    /**
     * Essential employee-linked demo accounts across major module owners.
     *
     * @var array<int, array{email: string, role: string, department_code: string, password: string, preferred_source_roles: array<int, string>, coverage_department_codes: array<int, string>}>
     */
    private const ESSENTIAL_EMPLOYEE_ACCOUNTS = [
        ['email' => 'demo.hr@ogamierp.local', 'role' => 'manager', 'department_code' => 'HR', 'password' => 'DemoHr@1234!', 'preferred_source_roles' => ['manager', 'head', 'officer', 'staff'], 'coverage_department_codes' => ['HR']],
        ['email' => 'demo.hr.head@ogamierp.local', 'role' => 'head', 'department_code' => 'HR', 'password' => 'DemoHrHead@1234!', 'preferred_source_roles' => ['head', 'manager', 'officer', 'staff'], 'coverage_department_codes' => ['HR']],
        ['email' => 'demo.hr.staff@ogamierp.local', 'role' => 'staff', 'department_code' => 'HR', 'password' => 'DemoHrStaff@1234!', 'preferred_source_roles' => ['staff', 'officer', 'head', 'manager'], 'coverage_department_codes' => ['HR']],
        ['email' => 'demo.acctg@ogamierp.local', 'role' => 'manager', 'department_code' => 'ACCTG', 'password' => 'DemoAcctg@1234!', 'preferred_source_roles' => ['manager', 'head', 'officer', 'staff'], 'coverage_department_codes' => ['ACCTG']],
        ['email' => 'demo.prod@ogamierp.local', 'role' => 'manager', 'department_code' => 'PROD', 'password' => 'DemoProd@1234!', 'preferred_source_roles' => ['manager', 'head', 'officer', 'staff'], 'coverage_department_codes' => ['PROD', 'PPC', 'PLANT', 'MOLD', 'ISO']],
        ['email' => 'demo.ops.officer@ogamierp.local', 'role' => 'officer', 'department_code' => 'PROD', 'password' => 'DemoOpsOfficer@1234!', 'preferred_source_roles' => ['officer', 'staff', 'head', 'manager'], 'coverage_department_codes' => ['PROD', 'WH', 'QC']],
        ['email' => 'demo.qc@ogamierp.local', 'role' => 'manager', 'department_code' => 'QC', 'password' => 'DemoQc@1234!', 'preferred_source_roles' => ['manager', 'head', 'officer', 'staff'], 'coverage_department_codes' => ['QC']],
        ['email' => 'demo.wh@ogamierp.local', 'role' => 'manager', 'department_code' => 'WH', 'password' => 'DemoWh@1234!', 'preferred_source_roles' => ['manager', 'head', 'officer', 'staff'], 'coverage_department_codes' => ['WH']],
        ['email' => 'demo.purch@ogamierp.local', 'role' => 'manager', 'department_code' => 'PURCH', 'password' => 'DemoPurch@1234!', 'preferred_source_roles' => ['manager', 'head', 'officer', 'staff'], 'coverage_department_codes' => ['PURCH']],
        ['email' => 'demo.proc.officer@ogamierp.local', 'role' => 'officer', 'department_code' => 'PURCH', 'password' => 'DemoProcOfficer@1234!', 'preferred_source_roles' => ['officer', 'staff', 'head', 'manager'], 'coverage_department_codes' => ['PURCH']],
        ['email' => 'demo.sales@ogamierp.local', 'role' => 'manager', 'department_code' => 'SALES', 'password' => 'DemoSales@1234!', 'preferred_source_roles' => ['manager', 'head', 'officer', 'staff'], 'coverage_department_codes' => ['SALES']],
        ['email' => 'demo.maint@ogamierp.local', 'role' => 'manager', 'department_code' => 'MAINT', 'password' => 'DemoMaint@1234!', 'preferred_source_roles' => ['manager', 'head', 'officer', 'staff'], 'coverage_department_codes' => ['MAINT']],
        ['email' => 'demo.it@ogamierp.local', 'role' => 'manager', 'department_code' => 'IT', 'password' => 'DemoIt@1234!', 'preferred_source_roles' => ['manager', 'head', 'officer', 'staff'], 'coverage_department_codes' => ['IT']],
        ['email' => 'demo.approver@ogamierp.local', 'role' => 'vice_president', 'department_code' => 'ACCTG', 'password' => 'DemoVP@1234!', 'preferred_source_roles' => ['head', 'manager', 'officer', 'staff'], 'coverage_department_codes' => ['EXEC', 'ACCTG']],
    ];

    /**
     * Extra explicit keep-list emails not tied to employee personas.
     *
     * @var array<int, string>
     */
    private const EXPLICIT_KEEP_EMAILS = [
        'admin@ogamierp.local',
        'superadmin@ogamierp.local',
        'it.admin@ogamierp.local',
    ];

    /**
     * Known fixed credentials for non-employee demo/system accounts.
     *
     * @var array<string, string>
     */
    private const FIXED_ACCOUNT_PASSWORDS = [
        'admin@ogamierp.local' => 'Admin@1234567890!',
        'superadmin@ogamierp.local' => 'SuperAdmin@12345!',
        'it.admin@ogamierp.local' => 'Manager@12345!',
    ];

    private const FALLBACK_PASSWORD = 'DemoUser@1234!';

    public function run(): void
    {
        $essentialUserIds = $this->ensureEssentialEmployeeAccounts();
        $this->pruneNonEssentialInternalUsers($essentialUserIds);

        $this->syncAndCaptureAllCredentials();

        $this->printSummary();
        $this->writeCredentialsReport();
    }

    /**
     * @return array<int, int>
     */
    private function ensureEssentialEmployeeAccounts(): array
    {
        $keptUserIds = [];
        $usedEmployeeIds = [];

        foreach (self::ESSENTIAL_EMPLOYEE_ACCOUNTS as $account) {
            $departmentId = (int) (DB::table('departments')->where('code', $account['department_code'])->value('id') ?? 0);
            if ($departmentId === 0) {
                continue;
            }

            $employee = $this->resolveSourceEmployee($departmentId, $account['preferred_source_roles'], $usedEmployeeIds);

            if (! $employee) {
                continue;
            }

            $existingWithTargetEmail = User::query()->where('email', $account['email'])->first();
            $isNewAccount = $existingWithTargetEmail === null;
            if ($existingWithTargetEmail && $existingWithTargetEmail->employee_id && $existingWithTargetEmail->employee_id !== $employee->id) {
                Employee::query()->where('id', $existingWithTargetEmail->employee_id)->update(['user_id' => null]);
            }

            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => trim($employee->first_name.' '.$employee->last_name),
                    'password' => Hash::make($account['password']),
                    'department_id' => $departmentId,
                    'employee_id' => $employee->id,
                    'email_verified_at' => now(),
                    'password_changed_at' => now(),
                ]
            );

            $this->credentialMap[$account['email']] = $account['password'];

            $user->syncRoles([$account['role']]);

            if ($employee->user_id !== $user->id) {
                $employee->user_id = $user->id;
                $employee->save();
            }

            foreach ($account['coverage_department_codes'] as $index => $coverageCode) {
                $coverageDepartmentId = (int) (DB::table('departments')->where('code', $coverageCode)->value('id') ?? 0);
                if ($coverageDepartmentId === 0) {
                    continue;
                }

                DB::table('user_department_access')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'department_id' => $coverageDepartmentId,
                    ],
                    [
                        'is_primary' => $index === 0,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            $usedEmployeeIds[] = $employee->id;
            $keptUserIds[] = $user->id;

            if ($isNewAccount) {
                $position = (string) (DB::table('positions')->where('id', $employee->position_id)->value('title') ?? 'N/A');

                $this->newAccounts[] = [
                    'email' => $account['email'],
                    'password' => $account['password'],
                    'role' => $account['role'],
                    'position' => $position,
                ];
            }
        }

        return $keptUserIds;
    }

    private function syncAndCaptureAllCredentials(): void
    {
        /** @var \Illuminate\Support\Collection<int, User> $users */
        $users = User::query()
            ->with(['roles:id,name', 'employee:id,position_id'])
            ->orderBy('email')
            ->get();

        foreach ($users as $user) {
            $knownPassword = $this->credentialMap[$user->email]
                ?? self::FIXED_ACCOUNT_PASSWORDS[$user->email]
                ?? self::FALLBACK_PASSWORD;

            $user->password = Hash::make($knownPassword);
            $user->password_changed_at = now();
            $user->save();

            $this->credentialMap[$user->email] = $knownPassword;
        }
    }

    /**
     * @param array<int, string> $preferredRoles
     * @param array<int, int> $usedEmployeeIds
     */
    private function resolveSourceEmployee(int $departmentId, array $preferredRoles, array $usedEmployeeIds): ?Employee
    {
        /** @var \Illuminate\Support\Collection<int, Employee> $employees */
        $employees = Employee::query()
            ->where('department_id', $departmentId)
            ->whereNotIn('id', $usedEmployeeIds)
            ->get();

        if ($employees->isEmpty()) {
            return null;
        }

        foreach ($preferredRoles as $role) {
            foreach ($employees as $employee) {
                if (! $employee->user_id) {
                    continue;
                }

                $sourceUser = User::query()->find($employee->user_id);
                if ($sourceUser && $sourceUser->hasRole($role)) {
                    return $employee;
                }
            }
        }

        return $employees->first();
    }

    /**
     * @param array<int, int> $essentialUserIds
     */
    private function pruneNonEssentialInternalUsers(array $essentialUserIds): int
    {
        $explicitKeepIds = User::query()
            ->whereIn('email', self::EXPLICIT_KEEP_EMAILS)
            ->pluck('id')
            ->all();

        $protectedRoleIds = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super_admin', 'vendor', 'client']))
            ->pluck('id')
            ->all();

        $portalLinkedIds = User::query()
            ->whereNotNull('vendor_id')
            ->orWhereNotNull('client_id')
            ->pluck('id')
            ->all();

        $keepIds = array_values(array_unique(array_merge(
            $essentialUserIds,
            $explicitKeepIds,
            $protectedRoleIds,
            $portalLinkedIds
        )));

        $deleteIds = User::query()
            ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
            ->pluck('id')
            ->all();

        if ($deleteIds === []) {
            return 0;
        }

        Employee::query()->whereIn('user_id', $deleteIds)->update(['user_id' => null]);
        User::query()->whereIn('id', $deleteIds)->delete();

        return count($deleteIds);
    }

    private function printSummary(): void
    {
        $users = User::query()
            ->with(['roles:id,name', 'employee:id,position_id'])
            ->orderBy('email')
            ->get();

        $groupedRows = [];
        foreach ($users as $user) {
            $primaryRole = (string) ($user->roles->pluck('name')->first() ?? 'unassigned');
            $position = 'N/A';
            if ($user->employee?->position_id) {
                $position = (string) (DB::table('positions')->where('id', $user->employee->position_id)->value('title') ?? 'N/A');
            }

            $groupedRows[$primaryRole] ??= [];
            $groupedRows[$primaryRole][] = [
                $user->email,
                $this->credentialMap[$user->email] ?? self::FALLBACK_PASSWORD,
                $position,
            ];
        }

        $orderedRoleGroups = $this->sortRoleGroups($groupedRows);

        if ($this->command !== null) {
            $this->command->info('');
            $this->command->info('Active credential matrix (grouped by role)');

            foreach ($orderedRoleGroups as $role => $rows) {
                if ($rows === []) {
                    continue;
                }

                $this->command->info('');
                $this->command->info(strtoupper($role).' ('.count($rows).' accounts)');
                $this->command->table(
                    ['Email', 'Password', 'Position'],
                    $rows
                );
            }

            return;
        }

        // Fallback for non-interactive seeding contexts.
        logger()->info('Active credential matrix', ['grouped_rows' => $orderedRoleGroups]);
    }

    private function writeCredentialsReport(): void
    {
        $users = User::query()
            ->with(['roles:id,name'])
            ->orderBy('email')
            ->get();

        $groupedUsers = [];
        foreach ($users as $user) {
            $primaryRole = (string) ($user->roles->pluck('name')->first() ?? 'unassigned');
            $groupedUsers[$primaryRole] ??= [];
            $groupedUsers[$primaryRole][] = $user;
        }

        $orderedGroups = $this->sortRoleGroups($groupedUsers);

        $lines = [
            '# Ogami ERP - Test Account Credentials',
            '',
            'Generated: '.now()->format('Y-m-d H:i:s'),
            '',
            '## Login URL',
            'http://localhost:5173/login',
            '',
            '## Credentials by Role',
        ];

        foreach ($orderedGroups as $role => $roleUsers) {
            if ($roleUsers === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '### '.strtoupper($role).' ('.count($roleUsers).' accounts)';
            $lines[] = '';
            $lines[] = '| Email | Password |';
            $lines[] = '|-------|----------|';

            foreach ($roleUsers as $user) {
                $password = $this->credentialMap[$user->email] ?? self::FALLBACK_PASSWORD;
                $lines[] = sprintf('| %s | %s |', $user->email, $password);
            }
        }

        $lines[] = '';
        $lines[] = '## Default Rule';
        $lines[] = '';
        $lines[] = 'If an account is reseeded and not explicitly listed, the fallback password is:';
        $lines[] = '';
        $lines[] = self::FALLBACK_PASSWORD;
        $lines[] = '';

        file_put_contents(storage_path('app/test-credentials.md'), implode(PHP_EOL, $lines));

        if ($this->command !== null) {
            $this->command->info('Credentials report updated: storage/app/test-credentials.md');
        }
    }

    /**
     * @template T
     *
     * @param  array<string, array<int, T>>  $groups
     * @return array<string, array<int, T>>
     */
    private function sortRoleGroups(array $groups): array
    {
        $ordered = [];

        foreach (self::ROLE_OUTPUT_ORDER as $role) {
            if (array_key_exists($role, $groups)) {
                $ordered[$role] = $groups[$role];
                unset($groups[$role]);
            }
        }

        if ($groups !== []) {
            ksort($groups);
            foreach ($groups as $role => $rows) {
                $ordered[$role] = $rows;
            }
        }

        return $ordered;
    }
}
