<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_order_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_order_id')->constrained()->onDelete('cascade');

            // Who performed the action
            $table->foreignId('user_id')->nullable()->constrained()->comment('User who performed action (null if system)');
            $table->string('user_type', 50)->default('staff')->comment('staff or client');

            // Action details
            $table->string('action', 50)->comment('submitted, approved, rejected, negotiated, client_responded, cancelled, note_added');
            $table->string('from_status', 50)->nullable()->comment('Previous status');
            $table->string('to_status', 50)->nullable()->comment('New status');
            $table->text('comment')->nullable()->comment('Detailed comment for this action');

            // For negotiations - store what was proposed
            $table->json('metadata')->nullable()->comment('Additional data: proposed_delivery_date, proposed_price, etc.');

            $table->timestamps();

            // Indexes
            $table->index(['client_order_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_order_activities');
    }
};
