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
        Schema::create('requisition_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_requisition_id')->constrained('job_requisitions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index('job_requisition_id');
        });

        DB::statement("ALTER TABLE requisition_approvals ADD CONSTRAINT chk_ra_action CHECK (action IN ('approved','rejected','returned','noted'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_approvals');
    }
};
