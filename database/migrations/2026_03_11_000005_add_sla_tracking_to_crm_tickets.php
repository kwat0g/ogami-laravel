<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add SLA tracking columns to CRM tickets.
 *
 * sla_due_at        — deadline timestamp computed at ticket creation from priority SLA hours
 * first_response_at — timestamp of the first non-internal staff reply
 * sla_breached_at   — set by crm:mark-sla-breaches when the SLA deadline passes with no resolution
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_tickets', function (Blueprint $table): void {
            $table->timestamp('sla_due_at')->nullable()->after('resolved_at');
            $table->timestamp('first_response_at')->nullable()->after('sla_due_at');
            $table->timestamp('sla_breached_at')->nullable()->after('first_response_at');
        });
    }

    public function down(): void
    {
        Schema::table('crm_tickets', function (Blueprint $table): void {
            $table->dropColumn(['sla_due_at', 'first_response_at', 'sla_breached_at']);
        });
    }
};
