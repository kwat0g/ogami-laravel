<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance indexes to frequently queried columns.
 * Focuses on foreign keys, status columns, and date ranges.
 */
return new class extends Migration
{
    /**
     * Tables and their index configurations.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $indexes = [
        // AP / Vendor domain
        'vendor_invoices' => [
            ['column' => ['vendor_id', 'status'], 'name' => 'idx_vendor_invoices_vendor_status'],
            ['column' => 'due_date', 'name' => 'idx_vendor_invoices_due_date'],
            ['column' => 'status', 'name' => 'idx_vendor_invoices_status'],
            ['column' => 'invoice_date', 'name' => 'idx_vendor_invoices_invoice_date'],
        ],
        'vendor_payments' => [
            ['column' => 'vendor_invoice_id', 'name' => 'idx_vendor_payments_invoice_id'],
            ['column' => 'vendor_id', 'name' => 'idx_vendor_payments_vendor_id'],
            ['column' => 'payment_date', 'name' => 'idx_vendor_payments_payment_date'],
        ],
        'vendors' => [
            ['column' => 'is_active', 'name' => 'idx_vendors_is_active'],
            ['column' => 'accreditation_status', 'name' => 'idx_vendors_accreditation_status'],
            ['column' => 'tin', 'name' => 'idx_vendors_tin'],
        ],

        // AR / Customer domain
        'customer_invoices' => [
            ['column' => ['customer_id', 'status'], 'name' => 'idx_customer_invoices_customer_status'],
            ['column' => 'due_date', 'name' => 'idx_customer_invoices_due_date'],
            ['column' => 'status', 'name' => 'idx_customer_invoices_status'],
            ['column' => 'delivery_receipt_id', 'name' => 'idx_customer_invoices_dr_id'],
        ],
        'customer_payments' => [
            ['column' => 'customer_invoice_id', 'name' => 'idx_customer_payments_invoice_id'],
            ['column' => 'customer_id', 'name' => 'idx_customer_payments_customer_id'],
        ],
        'customers' => [
            ['column' => 'is_active', 'name' => 'idx_customers_is_active'],
            ['column' => 'tin', 'name' => 'idx_customers_tin'],
        ],

        // Procurement domain
        'purchase_requests' => [
            ['column' => ['department_id', 'status'], 'name' => 'idx_purchase_requests_dept_status'],
            ['column' => 'status', 'name' => 'idx_purchase_requests_status'],
            ['column' => 'requested_by_id', 'name' => 'idx_purchase_requests_requester'],
            ['column' => 'created_at', 'name' => 'idx_purchase_requests_created_at'],
        ],
        'purchase_orders' => [
            ['column' => ['vendor_id', 'status'], 'name' => 'idx_purchase_orders_vendor_status'],
            ['column' => 'purchase_request_id', 'name' => 'idx_purchase_orders_pr_id'],
            ['column' => 'status', 'name' => 'idx_purchase_orders_status'],
            ['column' => 'po_date', 'name' => 'idx_purchase_orders_po_date'],
            ['column' => 'delivery_date', 'name' => 'idx_purchase_orders_delivery_date'],
        ],
        'goods_receipts' => [
            ['column' => 'purchase_order_id', 'name' => 'idx_goods_receipts_po_id'],
            ['column' => 'vendor_id', 'name' => 'idx_goods_receipts_vendor_id'],
            ['column' => 'received_at', 'name' => 'idx_goods_receipts_received_at'],
        ],

        // Inventory domain
        'item_masters' => [
            ['column' => 'category_id', 'name' => 'idx_item_masters_category_id'],
            ['column' => 'type', 'name' => 'idx_item_masters_type'],
            ['column' => 'is_active', 'name' => 'idx_item_masters_is_active'],
            ['column' => 'item_code', 'name' => 'idx_item_masters_item_code'],
        ],
        'material_requisitions' => [
            ['column' => ['department_id', 'status'], 'name' => 'idx_material_requisitions_dept_status'],
            ['column' => 'status', 'name' => 'idx_material_requisitions_status'],
            ['column' => 'requested_by_id', 'name' => 'idx_material_requisitions_requester'],
        ],
        'stock_ledger' => [
            ['column' => ['item_id', 'created_at'], 'name' => 'idx_stock_ledger_item_created'],
            ['column' => 'reference_type', 'name' => 'idx_stock_ledger_reference_type'],
        ],

        // HR domain
        'employees' => [
            ['column' => 'department_id', 'name' => 'idx_employees_department_id'],
            ['column' => 'employment_status', 'name' => 'idx_employees_employment_status'],
            ['column' => 'employee_code', 'name' => 'idx_employees_employee_code'],
            ['column' => ['is_active', 'department_id'], 'name' => 'idx_employees_active_dept'],
        ],

        // Payroll domain
        'payroll_runs' => [
            ['column' => ['pay_period_id', 'status'], 'name' => 'idx_payroll_runs_period_status'],
            ['column' => 'status', 'name' => 'idx_payroll_runs_status'],
        ],
        'payroll_details' => [
            ['column' => ['payroll_run_id', 'employee_id'], 'name' => 'idx_payroll_details_run_employee'],
            ['column' => 'employee_id', 'name' => 'idx_payroll_details_employee_id'],
        ],

        // Accounting domain
        'journal_entries' => [
            ['column' => ['fiscal_period_id', 'status'], 'name' => 'idx_journal_entries_period_status'],
            ['column' => 'status', 'name' => 'idx_journal_entries_status'],
            ['column' => 'source_type', 'name' => 'idx_journal_entries_source_type'],
            ['column' => 'journal_entry_date', 'name' => 'idx_journal_entries_date'],
        ],
        'journal_entry_lines' => [
            ['column' => 'journal_entry_id', 'name' => 'idx_journal_entry_lines_je_id'],
            ['column' => 'chart_of_account_id', 'name' => 'idx_journal_entry_lines_account_id'],
        ],
        'chart_of_accounts' => [
            ['column' => 'account_type', 'name' => 'idx_chart_of_accounts_type'],
            ['column' => 'account_code', 'name' => 'idx_chart_of_accounts_code'],
            ['column' => 'is_active', 'name' => 'idx_chart_of_accounts_is_active'],
        ],

        // Production domain
        'production_orders' => [
            ['column' => ['status', 'target_start_date'], 'name' => 'idx_production_orders_status_start'],
            ['column' => 'status', 'name' => 'idx_production_orders_status'],
            ['column' => 'product_item_id', 'name' => 'idx_production_orders_product_item'],
        ],

        // Maintenance domain
        'maintenance_work_orders' => [
            ['column' => ['status', 'priority'], 'name' => 'idx_maintenance_work_orders_status_priority'],
            ['column' => 'equipment_id', 'name' => 'idx_maintenance_work_orders_equipment'],
            ['column' => 'assigned_to_id', 'name' => 'idx_maintenance_work_orders_assigned'],
            ['column' => 'scheduled_date', 'name' => 'idx_maintenance_work_orders_scheduled'],
        ],
        'equipment' => [
            ['column' => 'status', 'name' => 'idx_equipment_status'],
            ['column' => 'is_active', 'name' => 'idx_equipment_is_active'],
        ],

        // QC domain
        'inspections' => [
            ['column' => ['stage', 'status'], 'name' => 'idx_inspections_stage_status'],
            ['column' => 'status', 'name' => 'idx_inspections_status'],
            ['column' => 'inspector_id', 'name' => 'idx_inspections_inspector'],
            ['column' => 'inspection_date', 'name' => 'idx_inspections_date'],
        ],
        'non_conformance_reports' => [
            ['column' => 'status', 'name' => 'idx_ncrs_status'],
            ['column' => 'severity', 'name' => 'idx_ncrs_severity'],
        ],

        // Leave/Attendance domain
        'leave_requests' => [
            ['column' => ['employee_id', 'status'], 'name' => 'idx_leave_requests_employee_status'],
            ['column' => 'status', 'name' => 'idx_leave_requests_status'],
            ['column' => ['start_date', 'end_date'], 'name' => 'idx_leave_requests_dates'],
        ],
        'attendance_logs' => [
            ['column' => ['employee_id', 'log_date'], 'name' => 'idx_attendance_logs_employee_date'],
            ['column' => 'log_date', 'name' => 'idx_attendance_logs_log_date'],
        ],

        // Fixed Assets domain
        'fixed_assets' => [
            ['column' => ['category_id', 'status'], 'name' => 'idx_fixed_assets_category_status'],
            ['column' => 'status', 'name' => 'idx_fixed_assets_status'],
            ['column' => 'asset_code', 'name' => 'idx_fixed_assets_asset_code'],
        ],

        // Mold domain
        'mold_masters' => [
            ['column' => 'status', 'name' => 'idx_mold_masters_status'],
            ['column' => 'is_active', 'name' => 'idx_mold_masters_is_active'],
        ],

        // Delivery domain
        'delivery_receipts' => [
            ['column' => 'customer_id', 'name' => 'idx_delivery_receipts_customer_id'],
            ['column' => 'shipment_id', 'name' => 'idx_delivery_receipts_shipment_id'],
            ['column' => 'delivery_date', 'name' => 'idx_delivery_receipts_delivery_date'],
            ['column' => 'status', 'name' => 'idx_delivery_receipts_status'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexConfigs) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexConfigs as $config) {
                $columns = (array) $config['column'];
                $indexName = $config['name'];

                // Check if all columns exist before adding index
                $allColumnsExist = true;
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        $allColumnsExist = false;
                        break;
                    }
                }

                if ($allColumnsExist && ! $this->indexExists($table, $indexName)) {
                    Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                        $table->index($columns, $indexName);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexConfigs) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexConfigs as $config) {
                $indexName = $config['name'];

                if ($this->indexExists($table, $indexName)) {
                    Schema::table($table, function (Blueprint $table) use ($indexName) {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};
