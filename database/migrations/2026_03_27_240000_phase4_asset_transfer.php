<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.3 — Fixed Asset Transfer between departments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_transfers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets');
            $table->foreignId('from_department_id')->constrained('departments');
            $table->foreignId('to_department_id')->constrained('departments');
            $table->date('transfer_date');
            $table->string('status', 30)->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('requested_by_id')->constrained('users');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE asset_transfers ADD CONSTRAINT chk_asset_transfers_status CHECK (status IN ('pending','approved','completed','rejected'))");
        DB::statement('ALTER TABLE asset_transfers ADD CONSTRAINT chk_asset_transfers_dept CHECK (from_department_id != to_department_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transfers');
    }
};
