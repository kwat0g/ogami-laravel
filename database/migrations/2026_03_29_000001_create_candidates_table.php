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
        Schema::create('candidates', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->unique();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('source', 30)->default('walk_in');
            $table->string('resume_path', 500)->nullable();
            $table->string('linkedin_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('email');
            $table->index('source');
        });

        DB::statement("ALTER TABLE candidates ADD CONSTRAINT chk_candidates_source CHECK (source IN ('referral','walk_in','job_board','agency','internal'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
