<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Stub — full demo account + employee creation is handled by SampleDataSeeder.
 * This seeder is kept for backward compatibility with the DatabaseSeeder call order.
 */
class SampleAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // All user accounts and employee records are created by SampleDataSeeder.
        $this->command->info('✓ SampleAccountsSeeder: delegated to SampleDataSeeder.');
    }
}
