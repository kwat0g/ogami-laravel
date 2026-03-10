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
        Schema::create('vendor_rfq_vendors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rfq_id')->constrained('vendor_rfqs')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('status', 20)->default('invited');

            // Vendor quotation fields (populated when vendor responds)
            $table->unsignedBigInteger('quoted_amount_centavos')->nullable(); // total quoted price
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->text('vendor_remarks')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->boolean('is_selected')->default(false);

            $table->timestamps();

            $table->unique(['rfq_id', 'vendor_id']);
        });

        DB::statement("ALTER TABLE vendor_rfq_vendors ADD CONSTRAINT chk_vendor_rfq_vendors_status
            CHECK (status IN ('invited','quoted','declined'))");

        DB::statement("ALTER TABLE vendor_rfq_vendors ADD CONSTRAINT chk_vendor_rfq_vendors_amount
            CHECK (quoted_amount_centavos IS NULL OR quoted_amount_centavos >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_rfq_vendors');
    }
};
