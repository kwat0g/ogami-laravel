<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employee documents tracking for MediaLibrary categories.
 * Retention rules enforced at application layer via scheduled job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->string('category', 50)
                ->comment('resume|contract|id_document|medical_cert|bir_form|sss_form|tax_cert|other');
            $table->string('document_name', 250);
            $table->string('file_path', 500)->nullable();   // MediaLibrary fills this
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size_bytes')->nullable();
            $table->date('document_date')->nullable();      // date on the doc itself
            $table->date('expires_at')->nullable();         // e.g. medical certs
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'category']);
            $table->index('expires_at');
        });

        DB::statement("ALTER TABLE employee_documents ADD CONSTRAINT chk_doc_category
            CHECK (category IN ('resume','contract','id_document','medical_cert','bir_form','sss_form','tax_cert','other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
