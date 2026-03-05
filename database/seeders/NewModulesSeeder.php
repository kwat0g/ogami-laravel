<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Delivery\Models\DeliveryReceipt;
use App\Domains\Delivery\Models\DeliveryReceiptItem;
use App\Domains\Delivery\Models\Shipment;
use App\Domains\ISO\Models\AuditFinding;
use App\Domains\ISO\Models\ControlledDocument;
use App\Domains\ISO\Models\InternalAudit;
use App\Domains\Maintenance\Models\Equipment;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Maintenance\Models\PmSchedule;
use App\Domains\Mold\Models\MoldMaster;
use App\Domains\Mold\Models\MoldShotLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed demonstration data for the 7 new ERP modules introduced in Sprints A–F:
 *   Inventory, Production, QC/QA, Maintenance, Mold, Delivery/Logistics, ISO/IATF
 *
 * Prerequisites: SampleAccountsSeeder, DepartmentPositionSeeder, RolePermissionSeeder
 */
class NewModulesSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('email', 'admin@ogamierp.local')->value('id');

        if ($adminId === null) {
            $this->command->warn('NewModulesSeeder: admin user not found — skipping.');
            return;
        }

        $this->seedInventoryReference($adminId);
        $this->seedMaintenance($adminId);
        $this->seedMolds($adminId);
        $this->seedDelivery($adminId);
        $this->seedISO($adminId);

        $this->command->info('NewModulesSeeder: all new-module sample data seeded.');
    }

    // ── Inventory reference data ──────────────────────────────────────────────

    private function seedInventoryReference(int $adminId): void
    {
        // Item category
        $catId = DB::table('item_categories')->insertGetId([
            'code'        => 'RAW-MAT',
            'name'        => 'Raw Materials',
            'description' => 'Plastic pellets and raw resin inputs',
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Item masters (used later by delivery receipt items)
        foreach ([
            ['item_code' => 'RAW-001', 'name' => 'PP Resin Natural',      'unit_of_measure' => 'kg',  'type' => 'raw_material',  'reorder_point' => 500,  'reorder_qty' => 2000],
            ['item_code' => 'RAW-002', 'name' => 'HDPE Resin Black',       'unit_of_measure' => 'kg',  'type' => 'raw_material',  'reorder_point' => 300,  'reorder_qty' => 1000],
            ['item_code' => 'FGD-001', 'name' => 'Plastic Container 500ml','unit_of_measure' => 'pcs', 'type' => 'finished_good', 'reorder_point' => 1000, 'reorder_qty' => 5000],
        ] as $item) {
            DB::table('item_masters')->insertOrIgnore(array_merge($item, [
                'ulid'          => \Illuminate\Support\Str::ulid()->toString(),
                'category_id'   => $catId,
                'requires_iqc'  => true,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]));
        }

        // Warehouse location
        DB::table('warehouse_locations')->insertOrIgnore([
            'code'      => 'WH-A1',
            'name'      => 'Warehouse A – Rack 1',
            'zone'      => 'A',
            'bin'       => 'Rack-01',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Maintenance & Equipment ───────────────────────────────────────────────

    private function seedMaintenance(int $adminId): void
    {
        // Equipment 1 – operational injection moulding machine
        $eq1 = Equipment::create([
            'name'             => 'Injection Moulding Machine #1',
            'category'         => 'production',
            'manufacturer'     => 'Engel',
            'model_number'     => 'ES200/50',
            'serial_number'    => 'EM-2018-00123',
            'location'         => 'Production Floor A',
            'commissioned_on'  => '2018-06-01',
            'status'           => 'operational',
            'is_active'        => true,
            'created_by_id'    => $adminId,
        ]);

        // Equipment 2 – under maintenance
        $eq2 = Equipment::create([
            'name'             => 'Hydraulic Press #3',
            'category'         => 'production',
            'manufacturer'     => 'Schuler',
            'model_number'     => 'HP-320',
            'serial_number'    => 'HP-2015-00789',
            'location'         => 'Production Floor B',
            'commissioned_on'  => '2015-03-20',
            'status'           => 'under_maintenance',
            'is_active'        => true,
            'created_by_id'    => $adminId,
        ]);

        // Equipment 3 – decommissioned
        Equipment::create([
            'name'             => 'Old Conveyor Belt',
            'category'         => 'utility',
            'manufacturer'     => 'Interroll',
            'model_number'     => 'CB-100',
            'serial_number'    => 'CB-2010-00011',
            'location'         => 'Warehouse Storage',
            'commissioned_on'  => '2010-01-15',
            'status'           => 'decommissioned',
            'is_active'        => false,
            'created_by_id'    => $adminId,
        ]);

        // Work order 1 – open/critical corrective
        MaintenanceWorkOrder::create([
            'equipment_id'    => $eq2->id,
            'type'            => 'corrective',
            'priority'        => 'critical',
            'status'          => 'open',
            'title'           => 'Hydraulic oil leak investigation',
            'description'     => 'Unit developed a visible leak at the main cylinder seal during shift 2.',
            'reported_by_id'  => $adminId,
            'assigned_to_id'  => $adminId,
            'scheduled_date'  => now()->addDays(1)->toDateString(),
            'created_by_id'   => $adminId,
        ]);

        // Work order 2 – completed preventive
        MaintenanceWorkOrder::create([
            'equipment_id'    => $eq1->id,
            'type'            => 'preventive',
            'priority'        => 'normal',
            'status'          => 'completed',
            'title'           => '1 000-hour lubrication service',
            'description'     => 'Scheduled lubrication of toggle mechanism and tie bars.',
            'reported_by_id'  => $adminId,
            'assigned_to_id'  => $adminId,
            'scheduled_date'  => now()->subDays(10)->toDateString(),
            'completed_at'    => now()->subDays(8),
            'completion_notes' => 'All grease points refilled. No anomalies found.',
            'created_by_id'   => $adminId,
        ]);

        // PM schedules
        PmSchedule::create([
            'equipment_id'   => $eq1->id,
            'task_name'      => 'Monthly Lubrication',
            'frequency_days' => 30,
            'last_done_on'   => now()->subDays(8)->toDateString(),
            'is_active'      => true,
        ]);

        PmSchedule::create([
            'equipment_id'   => $eq2->id,
            'task_name'      => 'Weekly Visual Inspection',
            'frequency_days' => 7,
            'last_done_on'   => now()->subDays(3)->toDateString(),
            'is_active'      => true,
        ]);
    }

    // ── Mold Masters ──────────────────────────────────────────────────────────

    private function seedMolds(int $adminId): void
    {
        // Mold 1 – nearing critical (91 % shots used)
        $mold1 = MoldMaster::create([
            'name'          => 'Container 500ml – Cavity 4',
            'description'   => '4-cavity mould for 500 ml PP container',
            'cavity_count'  => 4,
            'material'      => 'P20 Tool Steel',
            'location'      => 'Mold Room Rack A',
            'max_shots'     => 500000,
            'status'        => 'active',
            'is_active'     => true,
            'created_by_id' => $adminId,
        ]);
        // Force current_shots to 91% of max to trigger isCritical()
        DB::table('mold_masters')
            ->where('id', $mold1->id)
            ->update(['current_shots' => 455000]);

        // Shot log for mold 1
        MoldShotLog::create([
            'mold_id'    => $mold1->id,
            'shot_count' => 5000,
            'operator_id' => $adminId,
            'log_date'   => now()->subDays(1)->toDateString(),
            'remarks'    => 'Normal production run, no defects observed.',
        ]);

        // Mold 2 – active, healthy
        MoldMaster::create([
            'name'          => 'Lid 500ml – Single Cavity',
            'description'   => 'Single-cavity mould for container lid',
            'cavity_count'  => 1,
            'material'      => 'H13 Tool Steel',
            'location'      => 'Mold Room Rack B',
            'max_shots'     => 800000,
            'status'        => 'active',
            'is_active'     => true,
            'created_by_id' => $adminId,
        ]);

        // Mold 3 – under maintenance
        MoldMaster::create([
            'name'          => 'Bucket 10L – Cavity 2',
            'description'   => '2-cavity mould for 10L industrial bucket',
            'cavity_count'  => 2,
            'material'      => 'P20 Tool Steel',
            'location'      => 'Maintenance Bay',
            'max_shots'     => 300000,
            'status'        => 'under_maintenance',
            'is_active'     => true,
            'created_by_id' => $adminId,
        ]);
    }

    // ── Delivery / Logistics ──────────────────────────────────────────────────

    private function seedDelivery(int $adminId): void
    {
        // Create a demo vendor (Accounts Payable)
        $vendorId = DB::table('vendors')->insertGetId([
            'name'           => 'Chinatown Resins Inc.',
            'tin'            => '000-123-456-000',
            'is_ewt_subject' => false,
            'is_active'      => true,
            'payment_terms'  => 'NET30',
            'address'        => '12 Resin Street, Tondo, Manila',
            'contact_person' => 'Juan Dela Cruz',
            'email'          => 'sales@chinatownresins.test',
            'phone'          => '+63 2 8888 0001',
            'created_by'     => $adminId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Create a demo customer (Accounts Receivable)
        $customerId = DB::table('customers')->insertGetId([
            'name'           => 'Ace Hardware Philippines',
            'tin'            => '000-987-654-000',
            'email'          => 'procurement@acehw.test',
            'phone'          => '+63 2 8999 0002',
            'contact_person' => 'Maria Cruz',
            'address'        => '1 Hardware Ave, Pasig, Metro Manila',
            'credit_limit'   => 500000,
            'is_active'      => true,
            'created_by'     => $adminId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $itemId = DB::table('item_masters')->where('item_code', 'RAW-001')->value('id');

        // Inbound DR – confirmed
        $dr1 = DeliveryReceipt::create([
            'vendor_id'      => $vendorId,
            'customer_id'    => null,
            'direction'      => 'inbound',
            'status'         => 'confirmed',
            'receipt_date'   => now()->subDays(5)->toDateString(),
            'remarks'        => 'Full delivery per PO-2026-0041.',
            'received_by_id' => $adminId,
            'created_by_id'  => $adminId,
        ]);

        if ($itemId) {
            DeliveryReceiptItem::create([
                'delivery_receipt_id' => $dr1->id,
                'item_master_id'      => $itemId,
                'quantity_expected'   => 1000,
                'quantity_received'   => 1000,
                'unit_of_measure'     => 'kg',
                'lot_batch_number'    => 'LOT-2026-0419',
                'remarks'             => null,
            ]);
        }

        // Outbound DR – draft
        $dr2 = DeliveryReceipt::create([
            'vendor_id'      => null,
            'customer_id'    => $customerId,
            'direction'      => 'outbound',
            'status'         => 'draft',
            'receipt_date'   => now()->toDateString(),
            'remarks'        => 'Partial shipment for SalesOrder SO-2026-0082.',
            'received_by_id' => $adminId,
            'created_by_id'  => $adminId,
        ]);

        $fgId = DB::table('item_masters')->where('item_code', 'FGD-001')->value('id');
        if ($fgId) {
            DeliveryReceiptItem::create([
                'delivery_receipt_id' => $dr2->id,
                'item_master_id'      => $fgId,
                'quantity_expected'   => 5000,
                'quantity_received'   => 0,
                'unit_of_measure'     => 'pcs',
                'lot_batch_number'    => null,
                'remarks'             => 'Pending loading',
            ]);
        }

        // Shipment linked to confirmed inbound DR
        Shipment::create([
            'delivery_receipt_id' => $dr1->id,
            'carrier'             => 'LBC Express',
            'tracking_number'     => 'LBC-2026-00129873',
            'shipped_at'          => now()->subDays(7),
            'estimated_arrival'   => now()->subDays(5)->toDateString(),
            'actual_arrival'      => now()->subDays(5)->toDateString(),
            'status'              => 'delivered',
            'notes'               => 'Delivered on time, no temperature excursion.',
            'created_by_id'       => $adminId,
        ]);
    }

    // ── ISO / IATF ────────────────────────────────────────────────────────────

    private function seedISO(int $adminId): void
    {
        // Controlled documents
        ControlledDocument::create([
            'title'           => 'Quality Manual',
            'category'        => 'quality',
            'document_type'   => 'manual',
            'owner_id'        => $adminId,
            'current_version' => 'v3.0',
            'status'          => 'approved',
            'effective_date'  => '2024-01-01',
            'review_date'     => '2026-01-01',
            'is_active'       => true,
            'created_by_id'   => $adminId,
        ]);

        ControlledDocument::create([
            'title'           => 'Injection Moulding Process Procedure',
            'category'        => 'production',
            'document_type'   => 'procedure',
            'owner_id'        => $adminId,
            'current_version' => 'v2.1',
            'status'          => 'approved',
            'effective_date'  => '2024-03-01',
            'review_date'     => '2026-03-01',
            'is_active'       => true,
            'created_by_id'   => $adminId,
        ]);

        ControlledDocument::create([
            'title'           => 'Incoming Quality Control Work Instruction',
            'category'        => 'quality',
            'document_type'   => 'work_instruction',
            'owner_id'        => $adminId,
            'current_version' => 'v1.0',
            'status'          => 'draft',
            'effective_date'  => null,
            'review_date'     => null,
            'is_active'       => true,
            'created_by_id'   => $adminId,
        ]);

        // Completed internal audit
        $audit1 = InternalAudit::create([
            'audit_scope'     => 'Production & Quality Control processes',
            'standard'        => 'IATF 16949:2016',
            'lead_auditor_id' => $adminId,
            'audit_date'      => now()->subMonths(3)->toDateString(),
            'status'          => 'completed',
            'summary'         => 'Three minor non-conformances raised; all closed by Dec 2025 target date.',
            'closed_at'       => now()->subMonths(1),
            'created_by_id'   => $adminId,
        ]);

        // Findings for completed audit
        $finding1 = AuditFinding::create([
            'audit_id'       => $audit1->id,
            'finding_type'   => 'nonconformity',
            'clause_ref'     => '8.4.1',
            'description'    => 'No evidence of supplier evaluation criteria being applied to sub-tier suppliers.',
            'severity'       => 'minor',
            'status'         => 'closed',
            'raised_by_id'   => $adminId,
            'closed_at'      => now()->subMonths(1),
        ]);

        DB::table('improvement_actions')->insert([
            'ulid'           => \Illuminate\Support\Str::ulid()->toString(),
            'finding_id'     => $finding1->id,
            'title'          => 'Update supplier qualification procedure',
            'description'    => 'Update supplier qualification procedure to include sub-tier suppliers. Training delivered to Purchasing team.',
            'action_type'    => 'corrective',
            'assigned_to_id' => $adminId,
            'due_date'       => now()->subMonths(1)->toDateString(),
            'status'         => 'completed',
            'completed_at'   => now()->subMonths(1),
            'created_by_id'  => $adminId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        AuditFinding::create([
            'audit_id'     => $audit1->id,
            'finding_type' => 'observation',
            'clause_ref'   => '9.1.1',
            'description'  => 'Monitoring frequency for process capability (Cpk) is not documented in the control plan.',
            'severity'     => 'minor',
            'status'       => 'closed',
            'raised_by_id' => $adminId,
            'closed_at'    => now()->subMonths(1),
        ]);

        // Planned internal audit
        InternalAudit::create([
            'audit_scope'     => 'HR & Training management',
            'standard'        => 'ISO 9001:2015',
            'lead_auditor_id' => $adminId,
            'audit_date'      => now()->addMonths(1)->toDateString(),
            'status'          => 'planned',
            'summary'         => null,
            'closed_at'       => null,
            'created_by_id'   => $adminId,
        ]);
    }
}
