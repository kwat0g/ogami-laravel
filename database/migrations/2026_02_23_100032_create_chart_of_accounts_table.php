<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chart of Accounts — hierarchical GL account tree.
 *
 * COA-001: code is a permanent unique identifier (unique index includes archived).
 * COA-002: only leaf nodes (no children) can be posted to — enforced in service.
 * COA-003: is_system accounts cannot be archived/renamed/deleted — enforced in policy.
 * COA-004: normal_balance cannot change after first JE line posted — enforced in service.
 * COA-005: archiving with non-zero balance rejected — enforced in service.
 * COA-006: max hierarchy depth = 5 — enforced in FormRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();           // COA-001: permanent unique code
            $table->string('name', 200);
            $table->string('account_type', 20);             // ASSET|LIABILITY|EQUITY|REVENUE|COGS|OPEX|TAX
            $table->foreignId('parent_id')->nullable()
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();
            $table->string('normal_balance', 6);            // DEBIT | CREDIT
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);   // COA-003: protected from archiving
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();                          // soft-delete = archive

            $table->index(['account_type', 'is_active']);
            $table->index('parent_id');
        });

        DB::statement("ALTER TABLE chart_of_accounts
            ADD CONSTRAINT chk_coa_account_type
            CHECK (account_type IN ('ASSET','LIABILITY','EQUITY','REVENUE','COGS','OPEX','TAX'))");

        DB::statement("ALTER TABLE chart_of_accounts
            ADD CONSTRAINT chk_coa_normal_balance
            CHECK (normal_balance IN ('DEBIT','CREDIT'))");

        // COA-001: unique code must include archived (deleted) accounts
        // The default $table->string('code')->unique() covers this because softDeletes
        // does NOT exclude deleted rows from unique constraints in PostgreSQL.
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
