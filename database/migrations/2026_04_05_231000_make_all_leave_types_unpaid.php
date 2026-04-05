<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE leave_types ALTER COLUMN is_paid SET DEFAULT false');

        DB::table('leave_types')->update([
            'is_paid' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE leave_types ALTER COLUMN is_paid SET DEFAULT true');

        DB::table('leave_types')
            ->whereIn('code', ['VL', 'ML', 'BDAY', 'BL', 'PL'])
            ->update([
                'is_paid' => true,
                'updated_at' => now(),
            ]);

        DB::table('leave_types')
            ->where('code', 'OTH')
            ->update([
                'is_paid' => false,
                'updated_at' => now(),
            ]);
    }
};
