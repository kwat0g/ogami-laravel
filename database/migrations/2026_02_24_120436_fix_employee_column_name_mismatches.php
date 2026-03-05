<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix column name mismatches between the Employee Eloquent model and the
 * initial create_employees migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $renames = [
            ['employees', 'birth_date',          'date_of_birth'],
            ['employees', 'current_address',      'present_address'],
            ['employees', 'mobile_number',        'personal_phone'],
            ['employees', 'bank_account_number',  'bank_account_no'],
        ];

        foreach ($renames as [$table, $from, $to]) {
            if ($this->columnExists($table, $from) && ! $this->columnExists($table, $to)) {
                DB::statement("ALTER TABLE {$table} RENAME COLUMN {$from} TO {$to}");
            }
        }
    }

    public function down(): void
    {
        $renames = [
            ['employees', 'date_of_birth',    'birth_date'],
            ['employees', 'present_address',  'current_address'],
            ['employees', 'personal_phone',   'mobile_number'],
            ['employees', 'bank_account_no',  'bank_account_number'],
        ];

        foreach ($renames as [$table, $from, $to]) {
            if ($this->columnExists($table, $from) && ! $this->columnExists($table, $to)) {
                DB::statement("ALTER TABLE {$table} RENAME COLUMN {$from} TO {$to}");
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $count = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = ? AND column_name = ?",
            [$table, $column]
        );

        return (int) ($count->cnt ?? 0) > 0;
    }
};
