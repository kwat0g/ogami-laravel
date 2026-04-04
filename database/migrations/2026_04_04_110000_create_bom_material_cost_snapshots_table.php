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
        Schema::create('bom_material_cost_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('bom_id')->constrained('bill_of_materials')->cascadeOnDelete();
            $table->string('bom_version', 20);
            $table->unsignedBigInteger('material_cost_centavos')->default(0);
            $table->jsonb('component_lines')->nullable();
            $table->string('source', 30)->default('rollup');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bom_id', 'created_at']);
            $table->index(['source', 'created_at']);
        });

        DB::statement("ALTER TABLE bom_material_cost_snapshots ADD CONSTRAINT chk_bom_mcs_source CHECK (source IN ('rollup','auto_rollup','manual_recalc'))");
        DB::statement('ALTER TABLE bom_material_cost_snapshots ADD CONSTRAINT chk_bom_mcs_material_nonneg CHECK (material_cost_centavos >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_material_cost_snapshots');
    }
};
