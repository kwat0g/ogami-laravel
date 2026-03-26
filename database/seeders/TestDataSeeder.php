<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Test Data Seeder
 *
 * Adds realistic test data for attendance, OT, leave, payroll, invoices
 */
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Starting Test Data Seeding...');

        $this->seedAttendanceData();
        $this->seedVendorInvoices();
        $this->seedCustomerInvoices();

        $this->command->info('✓ Test Data Seeding Complete!');
    }

    private function seedAttendanceData(): void
    {
        $this->command->info('  → Seeding Attendance Data...');

        // Get employees
        $employees = DB::table('employees')->take(10)->get();

        $startDate = Carbon::now()->subDays(30);
        $count = 0;

        foreach ($employees as $employee) {
            // Create 30 days of attendance
            for ($i = 0; $i < 30; $i++) {
                $date = $startDate->copy()->addDays($i);

                // Skip weekends
                if ($date->isWeekend()) {
                    continue;
                }

                // Random clock in/out times
                $hourIn = rand(7, 9);
                $minIn = rand(0, 59);
                $hourOut = rand(16, 19);
                $minOut = rand(0, 59);

                // Some days with late arrival
                if (rand(1, 10) === 1) {
                    $hourIn = 9;
                    $minIn = rand(15, 45);
                }

                $timeIn = $date->copy()->setTime($hourIn, $minIn);
                $timeOut = $date->copy()->setTime($hourOut, $minOut);
                $lateMinutes = ($hourIn > 8) ? (($hourIn - 8) * 60 + $minIn) : 0;

                DB::table('attendance_logs')->insert([
                    'employee_id' => $employee->id,
                    'work_date' => $date->toDateString(),
                    'time_in' => $timeIn,
                    'time_out' => $timeOut,
                    'is_present' => true,
                    'is_absent' => false,
                    'late_minutes' => $lateMinutes,
                    'source' => 'manual',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }
        }

        $this->command->info("    ✓ Created {$count} attendance logs");
    }

    private function seedVendorInvoices(): void
    {
        $this->command->info('  → Seeding Vendor Invoices...');

        $vendors = DB::table('vendors')->take(3)->get();
        $count = 0;

        foreach ($vendors as $vendor) {
            // 2-3 invoices per vendor
            for ($i = 0; $i < rand(2, 3); $i++) {
                $amount = rand(500000, 2000000); // 5k-20k in centavos
                $status = ['pending', 'approved', 'paid'][array_rand(['pending', 'approved', 'paid'])];
                $date = now()->subDays(rand(5, 30));

                DB::table('vendor_invoices')->insert([
                    'vendor_id' => $vendor->id,
                    'invoice_number' => 'INV-'.strtoupper(substr($vendor->company_name ?? $vendor->name, 0, 3)).'-'.rand(1000, 9999),
                    'invoice_date' => $date,
                    'due_date' => $date->copy()->addDays(rand(15, 45)),
                    'subtotal' => $amount,
                    'tax_amount' => (int) ($amount * 0.12),
                    'total_amount' => (int) ($amount * 1.12),
                    'status' => $status,
                    'description' => 'Purchase of raw materials / supplies',
                    'created_at' => $date,
                    'updated_at' => now(),
                ]);
                $count++;
            }
        }

        $this->command->info("    ✓ Created {$count} vendor invoices");
    }

    private function seedCustomerInvoices(): void
    {
        $this->command->info('  → Seeding Customer Invoices...');

        $customers = DB::table('customers')->take(3)->get();
        $count = 0;

        foreach ($customers as $customer) {
            // 2-3 invoices per customer
            for ($i = 0; $i < rand(2, 3); $i++) {
                $amount = rand(1000000, 5000000); // 10k-50k in centavos
                $status = ['draft', 'sent', 'paid', 'paid'][array_rand(['draft', 'sent', 'paid', 'paid'])];
                $date = now()->subDays(rand(5, 30));

                DB::table('customer_invoices')->insert([
                    'customer_id' => $customer->id,
                    'invoice_number' => 'CI-'.rand(10000, 99999),
                    'invoice_date' => $date,
                    'due_date' => $date->copy()->addDays(rand(15, 30)),
                    'subtotal' => $amount,
                    'tax_amount' => (int) ($amount * 0.12),
                    'total_amount' => (int) ($amount * 1.12),
                    'status' => $status,
                    'notes' => 'Sales of finished goods',
                    'created_at' => $date,
                    'updated_at' => now(),
                ]);
                $count++;
            }
        }

        $this->command->info("    ✓ Created {$count} customer invoices");
    }
}
