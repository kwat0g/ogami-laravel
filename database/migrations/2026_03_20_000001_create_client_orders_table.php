<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->comment('Client who placed the order');
            $table->string('order_reference')->unique()->comment('System-generated reference like CO-2026-00001');

            // Status workflow: pending → negotiating → approved → rejected → cancelled
            $table->enum('status', [
                'pending',      // Submitted, awaiting review
                'negotiating',  // Under discussion with client
                'approved',     // Approved, delivery schedule created
                'rejected',     // Rejected by sales
                'cancelled',    // Cancelled by client or system
            ])->default('pending');

            // Order details
            $table->date('requested_delivery_date')->nullable()->comment('When client wants delivery');
            $table->date('agreed_delivery_date')->nullable()->comment('Final agreed delivery date after negotiation');
            $table->bigInteger('total_amount_centavos')->default(0)->comment('Total order value in centavos');
            $table->text('client_notes')->nullable()->comment('Notes from client when submitting');
            $table->text('internal_notes')->nullable()->comment('Internal notes for sales team');

            // Rejection/negotiation reason
            $table->string('rejection_reason')->nullable()->comment('Why order was rejected');
            $table->string('negotiation_reason')->nullable()->comment('Type of negotiation: stock_low, production_delay, price_change, partial_fulfillment, other');
            $table->text('negotiation_notes')->nullable()->comment('Details of negotiation proposal');

            // Links to other modules
            $table->foreignId('delivery_schedule_id')->nullable()->constrained()->comment('Created after approval');
            $table->foreignId('approved_by')->nullable()->constrained('users')->comment('Sales user who approved');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();

            // Tracking
            $table->foreignId('submitted_by')->nullable()->constrained('users')->comment('Client portal user who submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('order_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_orders');
    }
};
