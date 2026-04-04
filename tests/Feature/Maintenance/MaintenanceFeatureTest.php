<?php

declare(strict_types=1);

use App\Domains\HR\Models\Department;
use App\Domains\Inventory\Models\ItemCategory;
use App\Domains\Inventory\Models\ItemMaster;
use App\Domains\Inventory\Models\StockBalance;
use App\Domains\Inventory\Models\WarehouseLocation;
use App\Domains\Maintenance\Models\Equipment;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Domains\Maintenance\Models\WorkOrderPart;
use App\Models\User;
use Database\Seeders\DepartmentModuleAssignmentSeeder;
use Database\Seeders\DepartmentPositionSeeder;
use Database\Seeders\ModulePermissionSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'maintenance');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ModuleSeeder::class);
    $this->seed(ModulePermissionSeeder::class);
    $this->seed(DepartmentPositionSeeder::class);
    $this->seed(DepartmentModuleAssignmentSeeder::class);

    $maintDept = Department::where('code', 'MAINT')->first();
    $this->manager = User::factory()->create();
    $this->manager->assignRole('manager');
    $this->manager->departments()->attach($maintDept->id, ['is_primary' => true]);
});

it('lists equipment', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/maintenance/equipment')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates equipment', function () {
    $this->actingAs($this->manager)
        ->postJson('/api/v1/maintenance/equipment', [
            'name' => 'CNC Machine 01',
            'asset_tag' => 'EQ-001',
            'type' => 'machine',
            'location' => 'Plant Floor',
            'status' => 'operational',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'CNC Machine 01');
});

it('lists work orders', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/v1/maintenance/work-orders')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates a work order', function () {
    $equipment = Equipment::create([
        'name' => 'Test Machine',
        'asset_tag' => 'EQ-TEST',
        'type' => 'machine',
        'status' => 'operational',
    ]);

    $this->actingAs($this->manager)
        ->postJson('/api/v1/maintenance/work-orders', [
            'title' => 'Oil change',
            'type' => 'preventive',
            'priority' => 'normal',
            'equipment_id' => $equipment->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Oil change');
});

it('runs maintenance full workflow: create WO -> add part -> start -> complete with stock issue', function () {
    $equipment = Equipment::create([
        'name' => 'Hydraulic Press 01',
        'asset_tag' => 'EQ-HYD-001',
        'type' => 'machine',
        'status' => 'operational',
    ]);

    $category = ItemCategory::create([
        'code' => 'SPARE',
        'name' => 'Spare Parts',
        'is_active' => true,
    ]);

    $item = ItemMaster::create([
        'item_code' => 'SP-BELT-001',
        'name' => 'Timing Belt',
        'category_id' => $category->id,
        'type' => 'spare_part',
        'unit_of_measure' => 'pcs',
        'is_active' => true,
        'reorder_point' => 0,
        'reorder_qty' => 0,
    ]);

    $location = WarehouseLocation::create([
        'code' => 'WH-MAIN-01',
        'name' => 'Main Warehouse',
        'is_active' => true,
    ]);

    StockBalance::create([
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity_on_hand' => 10,
    ]);

    $createResponse = $this->actingAs($this->manager)
        ->postJson('/api/v1/maintenance/work-orders', [
            'title' => 'Replace worn timing belt',
            'type' => 'corrective',
            'priority' => 'high',
            'equipment_id' => $equipment->id,
            'scheduled_date' => now()->addDay()->toDateString(),
        ])
        ->assertCreated();

    $workOrderId = (int) $createResponse->json('data.id');
    $workOrderUlid = (string) $createResponse->json('data.ulid');

    $this->actingAs($this->manager)
        ->postJson("/api/v1/maintenance/work-orders/{$workOrderUlid}/parts", [
            'item_id' => $item->id,
            'location_id' => $location->id,
            'qty_required' => 2,
            'remarks' => 'Planned replacement part',
        ])
        ->assertCreated();

    $this->actingAs($this->manager)
        ->patchJson("/api/v1/maintenance/work-orders/{$workOrderUlid}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->actingAs($this->manager)
        ->patchJson("/api/v1/maintenance/work-orders/{$workOrderUlid}/complete", [
            'completion_notes' => 'Belt replaced successfully and machine tested.',
            'labor_hours' => 1.5,
            'actual_completion_date' => now()->toDateString(),
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $workOrder = MaintenanceWorkOrder::findOrFail($workOrderId);
    expect($workOrder->status)->toBe('completed');
    expect($workOrder->labor_hours)->toBe(1.5);

    $part = WorkOrderPart::where('work_order_id', $workOrderId)->firstOrFail();
    expect($part->qty_consumed)->toBe(2.0);

    $stock = StockBalance::where('item_id', $item->id)
        ->where('location_id', $location->id)
        ->firstOrFail();
    expect((float) $stock->quantity_on_hand)->toBe(8.0);
});
