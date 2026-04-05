<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement('ALTER TABLE leave_requests DROP CONSTRAINT IF EXISTS leave_requests_status_check');
            DB::statement('ALTER TABLE leave_requests DROP CONSTRAINT IF EXISTS chk_lr_status');

            if (Schema::hasColumn('leave_requests', 'manager_checked_by')) {
                Schema::table('leave_requests', function (Blueprint $table): void {
                    $table->dropForeign(['manager_checked_by']);
                });

                DB::statement('ALTER TABLE leave_requests RENAME COLUMN manager_checked_by TO manager_approved_by');
                DB::statement('ALTER TABLE leave_requests RENAME COLUMN manager_check_remarks TO manager_approved_remarks');
                DB::statement('ALTER TABLE leave_requests RENAME COLUMN manager_checked_at TO manager_approved_at');

                Schema::table('leave_requests', function (Blueprint $table): void {
                    $table->foreign('manager_approved_by')->references('id')->on('users')->nullOnDelete();
                });
            }

            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->string('requester_type')->nullable()->after('submitted_by');
                $table->foreignId('hr_approved_by')->nullable()->after('manager_approved_at')
                    ->constrained('users')->nullOnDelete();
                $table->text('hr_remarks')->nullable()->after('hr_approved_by');
                $table->timestamp('hr_approved_at')->nullable()->after('hr_remarks');
            });

            DB::table('leave_requests')
                ->where('status', 'manager_checked')
                ->update(['status' => 'head_approved']);
            DB::table('leave_requests')
                ->where('status', 'ga_processed')
                ->update(['status' => 'hr_approved']);

            $requests = DB::table('leave_requests')
                ->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
                ->leftJoin('users', 'employees.user_id', '=', 'users.id')
                ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                ->leftJoin('model_has_roles', function ($join): void {
                    $join->on('model_has_roles.model_id', '=', 'users.id')
                        ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
                })
                ->leftJoin('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->select('leave_requests.id', 'departments.code as department_code', 'roles.name as role_name')
                ->orderBy('leave_requests.id')
                ->get()
                ->groupBy('id');

            foreach ($requests as $leaveRequestId => $rows) {
                $roleNames = collect($rows)->pluck('role_name')->filter()->unique()->values();
                $departmentCode = (string) (collect($rows)->pluck('department_code')->filter()->first() ?? '');

                $requesterType = match (true) {
                    $roleNames->contains('manager') && $departmentCode === 'HR' => 'hr_manager',
                    $roleNames->contains('manager') => 'dept_manager',
                    $roleNames->intersect(['head', 'officer'])->isNotEmpty() => 'head_officer',
                    default => 'staff',
                };

                DB::table('leave_requests')->where('id', $leaveRequestId)->update([
                    'requester_type' => $requesterType,
                ]);
            }

            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->string('requester_type')->nullable(false)->change();
            });

            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->dropForeign(['ga_processed_by']);
                $table->dropColumn([
                    'ga_processed_by',
                    'ga_remarks',
                    'ga_processed_at',
                    'action_taken',
                    'beginning_balance',
                    'applied_days',
                    'ending_balance',
                ]);
            });

            DB::statement("
                ALTER TABLE leave_requests
                ADD CONSTRAINT leave_requests_status_check
                CHECK (status IN (
                    'draft',
                    'submitted',
                    'head_approved',
                    'manager_approved',
                    'hr_approved',
                    'approved',
                    'rejected',
                    'cancelled'
                ))
            ");
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::statement('ALTER TABLE leave_requests DROP CONSTRAINT IF EXISTS leave_requests_status_check');

            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->foreignId('ga_processed_by')->nullable()->after('manager_approved_at')
                    ->constrained('users')->nullOnDelete();
                $table->text('ga_remarks')->nullable()->after('ga_processed_by');
                $table->timestamp('ga_processed_at')->nullable()->after('ga_remarks');
                $table->string('action_taken')->nullable()->after('ga_processed_at');
                $table->float('beginning_balance')->nullable()->after('action_taken');
                $table->float('applied_days')->nullable()->after('beginning_balance');
                $table->float('ending_balance')->nullable()->after('applied_days');
                $table->dropForeign(['hr_approved_by']);
                $table->dropColumn(['requester_type', 'hr_approved_by', 'hr_remarks', 'hr_approved_at']);
            });

            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->dropForeign(['manager_approved_by']);
            });

            DB::statement('ALTER TABLE leave_requests RENAME COLUMN manager_approved_by TO manager_checked_by');
            DB::statement('ALTER TABLE leave_requests RENAME COLUMN manager_approved_remarks TO manager_check_remarks');
            DB::statement('ALTER TABLE leave_requests RENAME COLUMN manager_approved_at TO manager_checked_at');

            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->foreign('manager_checked_by')->references('id')->on('users')->nullOnDelete();
            });

            DB::table('leave_requests')
                ->where('status', 'manager_approved')
                ->update(['status' => 'manager_checked']);
            DB::table('leave_requests')
                ->where('status', 'hr_approved')
                ->update(['status' => 'ga_processed']);

            DB::statement("
                ALTER TABLE leave_requests
                ADD CONSTRAINT leave_requests_status_check
                CHECK (status IN (
                    'draft',
                    'submitted',
                    'head_approved',
                    'manager_checked',
                    'ga_processed',
                    'approved',
                    'rejected',
                    'cancelled'
                ))
            ");
        });
    }
};
