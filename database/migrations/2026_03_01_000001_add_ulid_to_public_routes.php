<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Add a `ulid` column to every model that is exposed in URL paths.
 *
 * Rationale: integer PKs in URLs allow trivial enumeration
 * (GET /hr/employees/2, /3, /4 …). ULIDs are opaque, sortable, and
 * globally unique — identical security benefit to UUIDs but URL-friendly.
 *
 * Tables affected:
 *   employees, loans, payroll_runs, journal_entries,
 *   vendor_invoices, customer_invoices, bank_reconciliations
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'employees',
        'loans',
        'payroll_runs',
        'journal_entries',
        'vendor_invoices',
        'customer_invoices',
        'bank_reconciliations',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            // Add nullable first so we can back-fill any existing rows.
            Schema::table($table, function (Blueprint $t): void {
                $t->string('ulid', 26)->nullable()->unique()->after('id')
                    ->comment('Public-facing opaque ID used in URL routes');
            });

            // Back-fill any existing rows with a proper ULID.
            // For fresh installs this is a no-op since no rows exist yet;
            // the model's creating event handles all future rows.
            $ids = DB::table($table)->whereNull('ulid')->pluck('id');
            foreach ($ids as $id) {
                DB::table($table)->where('id', $id)->update(['ulid' => (string) Str::ulid()]);
            }

            // Now safe to make NOT NULL.
            Schema::table($table, function (Blueprint $t): void {
                $t->string('ulid', 26)->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->dropColumn('ulid');
            });
        }
    }
};
