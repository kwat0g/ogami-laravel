<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-002: Configurable GL account mapping table.
 *
 * Replaces all hardcoded ChartOfAccount::where('code', '2001') lookups
 * with a configurable mapping table. Finance can remap accounts without
 * a code deployment.
 *
 * Lookup key: module + event + optional sub_key (e.g., item category, tax type).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 50)->comment('Domain module: payroll, ap, ar, production, fixed_assets, loan, tax, inventory');
            $table->string('event', 80)->comment('Posting event: PAYROLL_POST, INVOICE_POST, DEPRECIATION, DISPOSAL, etc.');
            $table->string('sub_key', 80)->nullable()->comment('Optional sub-key: tax_type, item_category, etc.');
            $table->string('side', 10)->comment('debit or credit');
            $table->foreignId('account_id')->constrained('chart_of_accounts')->comment('FK to chart_of_accounts');
            $table->text('description')->nullable()->comment('Human-readable description of this mapping');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['module', 'event', 'sub_key', 'side'], 'uq_account_mapping');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
    }
};
