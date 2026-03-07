<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C2: Extend AP Invoice (vendor_invoices) approval workflow to 5 steps:
 *   draft → pending_approval → head_noted → manager_checked → officer_reviewed → approved
 *
 * Adds audit columns for the three new intermediate steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->foreignId('head_noted_by')->nullable()->constrained('users')->nullOnDelete()
                ->after('submitted_at');
            $table->timestamp('head_noted_at')->nullable()->after('head_noted_by');
            $table->foreignId('manager_checked_by')->nullable()->constrained('users')->nullOnDelete()
                ->after('head_noted_at');
            $table->timestamp('manager_checked_at')->nullable()->after('manager_checked_by');
            $table->foreignId('officer_reviewed_by')->nullable()->constrained('users')->nullOnDelete()
                ->after('manager_checked_at');
            $table->timestamp('officer_reviewed_at')->nullable()->after('officer_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->dropForeign(['head_noted_by']);
            $table->dropForeign(['manager_checked_by']);
            $table->dropForeign(['officer_reviewed_by']);
            $table->dropColumn([
                'head_noted_by', 'head_noted_at',
                'manager_checked_by', 'manager_checked_at',
                'officer_reviewed_by', 'officer_reviewed_at',
            ]);
        });
    }
};
