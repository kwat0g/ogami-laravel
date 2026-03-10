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
        Schema::create('crm_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('client_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ticket_number', 20)->unique(); // TKT-YYYY-NNNNN
            $table->string('subject', 200);
            $table->text('description');
            $table->string('type', 20);
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('open');
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority']);
            $table->index('customer_id');
            $table->index('assigned_to_id');
        });

        DB::statement("
            ALTER TABLE crm_tickets
            ADD CONSTRAINT chk_ticket_type
                CHECK (type IN ('complaint','inquiry','request'))
        ");

        DB::statement("
            ALTER TABLE crm_tickets
            ADD CONSTRAINT chk_ticket_priority
                CHECK (priority IN ('low','normal','high','critical'))
        ");

        DB::statement("
            ALTER TABLE crm_tickets
            ADD CONSTRAINT chk_ticket_status
                CHECK (status IN ('open','in_progress','pending_client','resolved','closed'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tickets');
    }
};
