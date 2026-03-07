<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add soft-delete (deleted_at) columns to all tables that previously
 * supported hard delete.  Immutable ledger/log tables are intentionally
 * excluded (stock_ledger, vat_ledger, journal_entry_lines, attendance_logs,
 * bank_transactions, payroll_details, etc.).
 */
return new class extends Migration
{
    /** @var list<string> Tables that already have deleted_at — skip them */
    private array $alreadyHave = [
        'departments', 'positions', 'salary_grades', 'employees',
        'employee_documents', 'shift_schedules', 'leave_types', 'leave_requests',
        'loan_types', 'loans', 'payroll_runs', 'chart_of_accounts',
        'vendors', 'vendor_invoices', 'customers', 'customer_invoices',
        'customer_advance_payments', 'bank_accounts', 'pay_periods',
        'purchase_requests', 'purchase_orders', 'goods_receipts',
    ];

    /** @var list<string> Tables to add deleted_at to */
    private array $tables = [
        // Admin / rate config
        'train_tax_brackets',
        'sss_contribution_tables',
        'philhealth_premium_tables',
        'pagibig_contribution_tables',
        'minimum_wage_rates',
        'holiday_calendars',
        'ewt_rates',

        // Attendance / HR
        'overtime_requests',

        // Payroll
        'payroll_adjustments',

        // Inventory
        'item_categories',
        'item_masters',
        'warehouse_locations',
        'lot_batches',
        'material_requisitions',
        'material_requisition_items',

        // Procurement items
        'purchase_request_items',
        'purchase_order_items',
        'goods_receipt_items',

        // Production
        'bill_of_materials',
        'bom_components',
        'delivery_schedules',
        'production_orders',
        'production_output_logs',

        // QC
        'inspection_templates',
        'inspection_template_items',
        'inspections',
        'inspection_results',
        'non_conformance_reports',
        'capa_actions',

        // Maintenance
        'equipment',
        'maintenance_work_orders',
        'pm_schedules',

        // Mold
        'mold_masters',
        'mold_shot_logs',

        // Delivery
        'shipments',
        'delivery_receipts',
        'delivery_receipt_items',
        'vehicles',

        // ISO
        'controlled_documents',
        'document_revisions',
        'internal_audits',
        'audit_findings',
        'improvement_actions',

        // Finance (transactional — soft-archive only)
        'vendor_payments',
        'customer_payments',
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
