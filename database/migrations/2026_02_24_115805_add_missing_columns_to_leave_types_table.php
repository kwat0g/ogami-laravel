<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            // The LeaveTypeSeeder uses max_days_per_year; the original migration
            // created max_annual_days. Add the alias column if it doesn't exist.
            if (! Schema::hasColumn('leave_types', 'max_days_per_year')) {
                $table->unsignedSmallInteger('max_days_per_year')
                    ->default(0)
                    ->after('max_carry_over_days')
                    ->comment('Alias for max_annual_days; used by LeaveTypeSeeder');
            }

            // The seeder also inserts requires_documentation.
            if (! Schema::hasColumn('leave_types', 'requires_documentation')) {
                $table->boolean('requires_documentation')
                    ->default(false)
                    ->after('requires_approval')
                    ->comment('LV: medical cert, birth cert, etc. required before approval');
            }

            // The seeder sends null for monthly_accrual_days on non-accruing leave types.
            // Change from NOT NULL decimal(5,2) DEFAULT 0.00 to nullable decimal(5,2).
            $table->decimal('monthly_accrual_days', 5, 2)
                ->nullable()
                ->default(null)
                ->comment('Monthly accruing days; NULL = leave is granted as a fixed block (not accrued)')
                ->change();
        });

        // Expand the category constraint to include vawc and lwop (both used by LeaveTypeSeeder)
        DB::statement('ALTER TABLE leave_types DROP CONSTRAINT IF EXISTS chk_lt_category');
        DB::statement("ALTER TABLE leave_types ADD CONSTRAINT chk_lt_category CHECK (category IN ('sick','vacation','service_incentive','maternity','paternity','solo_parent','bereavement','vawc','lwop','other'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (Schema::hasColumn('leave_types', 'max_days_per_year')) {
                $table->dropColumn('max_days_per_year');
            }
            if (Schema::hasColumn('leave_types', 'requires_documentation')) {
                $table->dropColumn('requires_documentation');
            }
            // Revert monthly_accrual_days to NOT NULL decimal with default 0
            $table->decimal('monthly_accrual_days', 5, 2)
                ->nullable(false)
                ->default(0.00)
                ->change();
        });

        // Restore original category constraint
        DB::statement('ALTER TABLE leave_types DROP CONSTRAINT IF EXISTS chk_lt_category');
        DB::statement("ALTER TABLE leave_types ADD CONSTRAINT chk_lt_category CHECK (category IN ('sick','vacation','service_incentive','maternity','paternity','solo_parent','bereavement','other'))");
    }
};
