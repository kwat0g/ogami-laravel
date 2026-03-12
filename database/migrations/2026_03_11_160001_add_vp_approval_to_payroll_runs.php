<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-1: Add VP final approval step to payroll runs.
 *
 * Adds vp_approved_by_id and vp_approved_at columns to support the new
 * ACCTG_APPROVED → VP_APPROVED → DISBURSED workflow step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->foreignId('vp_approved_by_id')
                ->nullable()
                ->after('acctg_approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('vp_approved_at')
                ->nullable()
                ->after('vp_approved_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vp_approved_by_id');
            $table->dropColumn('vp_approved_at');
        });
    }
};
