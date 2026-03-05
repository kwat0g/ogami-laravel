<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 15 – Tax Module
 *
 * Creates:
 *   vat_ledger          — per-period input/output/net VAT tracking (VAT-004)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiscal_period_id')->unique();
            $table->decimal('input_vat', 15, 2)->default(0.00)
                ->comment('Accumulated from approved vendor invoices');
            $table->decimal('output_vat', 15, 2)->default(0.00)
                ->comment('Accumulated from approved customer invoices');
            // VAT-004: carry-forward when net_vat < 0 from prior period
            $table->decimal('carry_forward_from_prior', 15, 2)->default(0.00)
                ->comment('Excess input VAT carried from previous period');
            // Derived: net_vat = output_vat - input_vat
            $table->decimal('net_vat', 15, 2)->storedAs('output_vat - input_vat')
                ->comment('Positive = payable; negative = carry forward');
            // Derived: vat_payable = net_vat - carry_forward_from_prior (if >= 0)
            // VatLedger::getVatPayableAttribute() handles the carry-forward logic
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();

            $table->foreign('fiscal_period_id')->references('id')->on('fiscal_periods');
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_ledger');
    }
};
