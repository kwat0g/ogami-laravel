<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2C: Extend the existing vendors table with procurement-specific fields.
 *
 * IMPORTANT: Do NOT create a new vendors table.
 * The vendors table already exists in the AP domain (app/Domains/AP/Models/Vendor.php).
 * This migration adds accreditation tracking and banking details as additive columns.
 *
 * Convention: bank_account_no (not _number) — matches existing AP-domain conventions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('accreditation_status', 20)->default('pending')->after('is_active');
            $table->text('accreditation_notes')->nullable()->after('accreditation_status');
            $table->string('bank_name', 80)->nullable()->after('accreditation_notes');
            $table->string('bank_account_no', 50)->nullable()->after('bank_name');
            $table->string('bank_account_name', 150)->nullable()->after('bank_account_no');
            $table->string('payment_terms', 50)->nullable()->after('bank_account_name');
        });

        DB::statement("
            ALTER TABLE vendors ADD CONSTRAINT chk_vendor_accreditation_status
            CHECK (accreditation_status IN ('pending','accredited','suspended','blacklisted'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vendors DROP CONSTRAINT IF EXISTS chk_vendor_accreditation_status');
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'accreditation_status',
                'accreditation_notes',
                'bank_name',
                'bank_account_no',
                'bank_account_name',
                'payment_terms',
            ]);
        });
    }
};
