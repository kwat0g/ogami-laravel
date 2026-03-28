<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add soft-delete (deleted_at) columns to tables that were missed by the
 * earlier bulk migration (2026_03_07_200000). Covers Sales, Fixed Assets,
 * Journal Entries, and other transactional tables.
 */
return new class extends Migration
{
    /** @var list<string> Tables to add deleted_at to (only if missing) */
    private array $tables = [
        // Sales
        'sales_orders',
        'sales_order_items',
        'quotations',
        'quotation_items',
        'price_lists',
        'price_list_items',

        // Fixed Assets (categories already have it via 2026_03_11 migration)
        // Double-check these just in case
        'fixed_assets',
        'fixed_asset_categories',
        'asset_disposals',

        // Accounting
        'journal_entries',
        'journal_entry_templates',
        'recurring_journal_templates',
        'fiscal_periods',

        // Procurement
        'blanket_purchase_orders',
        'vendor_rfqs',
        'vendor_rfq_vendors',

        // AP
        'vendor_credit_notes',
        'payment_batches',
        'payment_batch_items',
        'vendor_fulfillment_notes',

        // AR
        'customer_credit_notes',
        'customer_advance_payments',
        'dunning_notices',

        // Loan
        'loans',
        'loan_types',
        'loan_amortization_schedules',

        // Leave
        'leave_types',
        'leave_requests',
        'leave_balances',

        // HR
        'employee_clearances',

        // Attendance
        'employee_shift_assignments',

        // CRM
        'tickets',
        'ticket_messages',
        'client_order_items',

        // Payroll
        'payroll_adjustments',

        // Delivery
        'delivery_routes',
        'vehicles',
        'impex_documents',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                DB::statement("ALTER TABLE {$table} ADD COLUMN deleted_at TIMESTAMPTZ NULL");
                DB::statement("CREATE INDEX IF NOT EXISTS idx_{$table}_deleted_at ON {$table} (deleted_at)");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                DB::statement("DROP INDEX IF EXISTS idx_{$table}_deleted_at");
                DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS deleted_at");
            }
        }
    }
};
