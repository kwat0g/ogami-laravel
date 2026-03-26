<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the company's 3 delivery vehicles:
 *   - 2 Delivery Trucks
 *   - 1 Mitsubishi L300 Van
 */
class FleetSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $vehicles = [
            [
                'ulid' => (string) Str::ulid(),
                'code' => 'TRUCK-001',
                'name' => 'Delivery Truck 1',
                'type' => 'truck',
                'make_model' => 'Delivery Truck',
                'plate_number' => 'TRK-000-1',
                'status' => 'active',
                'notes' => 'Primary delivery truck for local deliveries.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'ulid' => (string) Str::ulid(),
                'code' => 'TRUCK-002',
                'name' => 'Delivery Truck 2',
                'type' => 'truck',
                'make_model' => 'Delivery Truck',
                'plate_number' => 'TRK-000-2',
                'status' => 'active',
                'notes' => 'Secondary delivery truck for local deliveries.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'ulid' => (string) Str::ulid(),
                'code' => 'VAN-001',
                'name' => 'Mitsubishi L300 Van',
                'type' => 'van',
                'make_model' => 'Mitsubishi L300',
                'plate_number' => 'VAN-000-1',
                'status' => 'active',
                'notes' => 'L300 van used for smaller deliveries and export shipments.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('vehicles')->upsert(
            $vehicles,
            ['code'],
            ['name', 'type', 'make_model', 'plate_number', 'status', 'notes', 'updated_at'],
        );

        $this->command->info('✓ Fleet vehicles seeded (2 trucks, 1 L300 van).');
    }
}
