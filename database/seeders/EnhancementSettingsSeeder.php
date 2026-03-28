<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds system_settings with default values for all enhancement features.
 *
 * All new services are configurable via these keys -- administrators
 * can tune behavior through the Settings UI without code changes.
 */
class EnhancementSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ── Automation Toggles ────────────────────────────────────────
            ['key' => 'automation.so_confirmed.auto_create_production', 'value' => 'true', 'description' => 'Auto-create Production Order when Sales Order is confirmed'],
            ['key' => 'automation.quotation_accepted.auto_create_so', 'value' => 'true', 'description' => 'Auto-create Sales Order when Quotation is accepted'],
            ['key' => 'automation.production_output.auto_log_mold_shots', 'value' => 'true', 'description' => 'Auto-log mold shots when production output is recorded'],
            ['key' => 'automation.production_completed.auto_post_cost_gl', 'value' => 'true', 'description' => 'Auto-post cost variance GL entry when production order completes'],
            ['key' => 'automation.mrq_approved.auto_issue_single_location', 'value' => 'false', 'description' => 'Auto-issue MRQ stock for single-location warehouses'],
            ['key' => 'automation.ar_overdue.auto_create_dunning', 'value' => 'true', 'description' => 'Auto-create dunning notices for overdue AR invoices'],
            ['key' => 'automation.ap_due.auto_suggest_payment_batch', 'value' => 'false', 'description' => 'Auto-suggest payment batch for AP invoices due this week'],

            // ── Credit Control ────────────────────────────────────────────
            ['key' => 'credit.soft_limit_warn_only', 'value' => 'false', 'description' => 'When true, credit limit exceeded shows warning but allows order (soft block)'],

            // ── QC Gate ───────────────────────────────────────────────────
            ['key' => 'qc.allow_provisional_receipt', 'value' => 'false', 'description' => 'Allow GR confirmation without IQC (stock enters quarantine with 24hr deadline)'],
            ['key' => 'qc.vendor_qualified_threshold', 'value' => '95', 'description' => 'Vendor quality score threshold to bypass IQC gate (0-100)'],
            ['key' => 'qc.skip_lot_after_consecutive_passes', 'value' => '5', 'description' => 'Skip IQC after N consecutive passes from same vendor'],
            ['key' => 'qc.provisional_receipt_hours', 'value' => '24', 'description' => 'Hours allowed for provisional receipt before IQC must be completed'],

            // ── Leave Management ──────────────────────────────────────────
            ['key' => 'leave.department_min_staffing_pct', 'value' => '70', 'description' => 'Minimum staffing percentage per department (warn if leave would violate)'],
            ['key' => 'leave.team_max_concurrent_pct', 'value' => '30', 'description' => 'Maximum percentage of team on leave simultaneously'],
            ['key' => 'leave.monetization_divisor', 'value' => '26', 'description' => 'Working days per month for leave monetization calculation (DOLE standard)'],

            // ── CRM ──────────────────────────────────────────────────────
            ['key' => 'crm.lead_qualify_threshold', 'value' => '70', 'description' => 'Lead score threshold for auto-qualification (0-100)'],

            // ── Inventory Costing ─────────────────────────────────────────
            ['key' => 'inventory.default_costing_method', 'value' => 'standard', 'description' => 'Default costing method for new items (standard, fifo, weighted_average)'],
            ['key' => 'inventory.fifo_scope', 'value' => 'per_warehouse', 'description' => 'FIFO cost tracking scope (per_warehouse or global)'],
            ['key' => 'inventory.reservation_expiry_days', 'value' => '7', 'description' => 'Days before stock reservations auto-expire'],

            // ── Budget ───────────────────────────────────────────────────
            ['key' => 'budget.allow_overspend_with_approval', 'value' => 'false', 'description' => 'Allow spending over budget with executive approval'],

            // ── Procurement ──────────────────────────────────────────────
            ['key' => 'procurement.rfq_required_above_centavos', 'value' => '50000000', 'description' => 'RFQ required for purchases above this amount (in centavos, default P500K)'],
            ['key' => 'procurement.emergency_skip_budget', 'value' => 'false', 'description' => 'Allow emergency PRs to skip budget check'],

            // ── Payroll ──────────────────────────────────────────────────
            ['key' => 'payroll.separation_pay_multiplier', 'value' => '0.5', 'description' => 'Separation pay multiplier per year of service (0.5 = half month per year)'],

            // ── Company Info (for tax forms) ─────────────────────────────
            ['key' => 'company.name', 'value' => 'Ogami Manufacturing Corp.', 'description' => 'Company name for official documents'],
            ['key' => 'tax.company_tin', 'value' => '000-000-000-000', 'description' => 'Company TIN for BIR forms'],
            ['key' => 'tax.rdo_code', 'value' => '', 'description' => 'BIR Revenue District Office code'],
            ['key' => 'tax.company_address', 'value' => '', 'description' => 'Company address for tax filings'],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'description' => $setting['description'],
                    'updated_at' => now(),
                ],
            );
        }
    }
}
