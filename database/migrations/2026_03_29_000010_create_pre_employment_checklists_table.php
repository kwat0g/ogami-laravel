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
        Schema::create('pre_employment_checklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->text('waiver_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE pre_employment_checklists ADD CONSTRAINT chk_pec_status CHECK (status IN ('pending','in_progress','completed','waived'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_employment_checklists');
    }
};
