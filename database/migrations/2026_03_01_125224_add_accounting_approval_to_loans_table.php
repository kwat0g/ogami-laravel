<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Supervisor approval (first level review)
            $table->foreignId('supervisor_approved_by')->nullable()->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('supervisor_approved_at')->nullable()->after('supervisor_approved_by');
            $table->text('supervisor_remarks')->nullable()->after('supervisor_approved_at');

            // Accounting Manager approval (for financial control)
            $table->foreignId('accounting_approved_by')->nullable()->after('supervisor_remarks')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('accounting_approved_at')->nullable()->after('accounting_approved_by');
            $table->text('accounting_remarks')->nullable()->after('accounting_approved_at');

            // Disbursement tracking (who actually released the money)
            $table->foreignId('disbursed_by')->nullable()->after('disbursed_at')
                ->constrained('users')
                ->nullOnDelete();

            // GL Entry reference (links to journal entry)
            $table->foreignId('journal_entry_id')->nullable()->after('accounting_remarks')
                ->constrained('journal_entries')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['supervisor_approved_by']);
            $table->dropForeign(['accounting_approved_by']);
            $table->dropForeign(['disbursed_by']);
            $table->dropForeign(['journal_entry_id']);

            $table->dropColumn([
                'supervisor_approved_by',
                'supervisor_approved_at',
                'supervisor_remarks',
                'accounting_approved_by',
                'accounting_approved_at',
                'accounting_remarks',
                'disbursed_by',
                'journal_entry_id',
            ]);
        });
    }
};
