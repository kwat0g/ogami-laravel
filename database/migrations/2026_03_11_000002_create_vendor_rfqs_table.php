<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sequence for RFQ reference generation
        DB::statement('CREATE SEQUENCE IF NOT EXISTS vendor_rfq_seq START 1');

        Schema::create('vendor_rfqs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('rfq_reference', 30)->unique();     // RFQ-YYYY-MM-NNNNN
            $table->foreignId('purchase_request_id')
                ->nullable()
                ->constrained('purchase_requests')
                ->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->date('deadline_date')->nullable();
            $table->text('scope_description');                  // what items/services are needed
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE vendor_rfqs ADD CONSTRAINT chk_vendor_rfqs_status
            CHECK (status IN ('draft','sent','quote_received','closed','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_rfqs');
        DB::statement('DROP SEQUENCE IF EXISTS vendor_rfq_seq');
    }
};
