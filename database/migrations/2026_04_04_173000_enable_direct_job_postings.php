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
        Schema::table('job_postings', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('salary_grade_id')->nullable()->constrained('salary_grades')->nullOnDelete();
            $table->unsignedInteger('headcount')->nullable();

            $table->index('department_id');
            $table->index('position_id');
            $table->index('salary_grade_id');
        });

        DB::statement('ALTER TABLE job_postings ALTER COLUMN job_requisition_id DROP NOT NULL');
        DB::statement('ALTER TABLE hirings ALTER COLUMN job_requisition_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE hirings ALTER COLUMN job_requisition_id SET NOT NULL');
        DB::statement('ALTER TABLE job_postings ALTER COLUMN job_requisition_id SET NOT NULL');

        Schema::table('job_postings', function (Blueprint $table): void {
            $table->dropIndex(['department_id']);
            $table->dropIndex(['position_id']);
            $table->dropIndex(['salary_grade_id']);

            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('position_id');
            $table->dropConstrainedForeignId('salary_grade_id');
            $table->dropColumn('headcount');
        });
    }
};