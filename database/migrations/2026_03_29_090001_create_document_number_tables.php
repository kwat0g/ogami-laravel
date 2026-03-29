<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-013: Document numbering infrastructure.
 *
 * Two tables:
 * - document_number_configs: configurable format per document type
 * - document_number_sequences: current sequence counter per type + fiscal year
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_number_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 50)->unique()->comment('e.g. purchase_order, goods_receipt');
            $table->string('prefix', 20)->comment('Document prefix, e.g. PO, GR, SO');
            $table->string('separator', 5)->default('-')->comment('Separator between parts');
            $table->unsignedSmallInteger('zero_padding')->default(5)->comment('Number of digits in sequence');
            $table->boolean('reset_on_fiscal_year')->default(true)->comment('Reset sequence at start of fiscal year');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 50);
            $table->unsignedSmallInteger('fiscal_year')->default(0)->comment('0 = no fiscal year reset');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['document_type', 'fiscal_year'], 'uq_doc_seq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_sequences');
        Schema::dropIfExists('document_number_configs');
    }
};
