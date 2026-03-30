<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M7 FIX: Add missing database indexes identified in production audit.
 *
 * Without these indexes, approval workflow queries on purchase_requests,
 * vendor_invoices, and material_requisitions require full table scans
 * when filtering by intermediate status values.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Purchase requests: status is used in approval queue queries
        if (Schema::hasTable('purchase_requests') && Schema::hasColumn('purchase_requests', 'status')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->index('status', 'idx_pr_status');
            });
        }

        // Vendor invoices: status is used in AP approval dashboard
        if (Schema::hasTable('vendor_invoices') && Schema::hasColumn('vendor_invoices', 'status')) {
            Schema::table('vendor_invoices', function (Blueprint $table) {
                $table->index('status', 'idx_vi_status');
            });
        }

        // Material requisitions: status is used in warehouse fulfillment queue
        if (Schema::hasTable('material_requisitions') && Schema::hasColumn('material_requisitions', 'status')) {
            Schema::table('material_requisitions', function (Blueprint $table) {
                $table->index('status', 'idx_mrq_status');
            });
        }

        // Payroll details: payroll_run_id is used in aggregation queries (GL posting, breakdown)
        if (Schema::hasTable('payroll_details') && Schema::hasColumn('payroll_details', 'payroll_run_id')) {
            // Check if index already exists
            if (! $this->indexExists('payroll_details', 'payroll_run_id')) {
                Schema::table('payroll_details', function (Blueprint $table) {
                    $table->index('payroll_run_id', 'idx_pd_run_id');
                });
            }
        }

        // Journal entries: fiscal_period_id + status for period-close validation
        if (Schema::hasTable('journal_entries') && Schema::hasColumn('journal_entries', 'fiscal_period_id')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->index(['fiscal_period_id', 'status'], 'idx_je_period_status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropIndex('idx_pr_status');
        });

        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_vi_status');
        });

        Schema::table('material_requisitions', function (Blueprint $table) {
            $table->dropIndex('idx_mrq_status');
        });

        if ($this->indexExists('payroll_details', 'idx_pd_run_id')) {
            Schema::table('payroll_details', function (Blueprint $table) {
                $table->dropIndex('idx_pd_run_id');
            });
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_je_period_status');
        });
    }

    private function indexExists(string $table, string $column): bool
    {
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $index) {
            if (in_array($column, $index['columns'], true)) {
                return true;
            }
        }

        return false;
    }
};
