<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.3 — ISO Document Distribution tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_distributions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('controlled_document_id')->constrained('controlled_documents')->cascadeOnDelete();
            $table->foreignId('distributed_to_id')->constrained('users');
            $table->string('status', 30)->default('pending');
            $table->timestamp('distributed_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('distributed_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE document_distributions ADD CONSTRAINT chk_doc_dist_status CHECK (status IN ('pending','distributed','acknowledged','recalled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('document_distributions');
    }
};
