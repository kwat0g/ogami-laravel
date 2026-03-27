<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.5 — CRM sub-modules: Lead, Contact, Opportunity, Activity.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Leads ────────────────────────────────────────────────────────────
        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('source', 30)->default('website');
            $table->string('status', 30)->default('new');
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE crm_leads ADD CONSTRAINT chk_crm_leads_source CHECK (source IN ('website','referral','trade_show','cold_call','social_media','other'))");
        DB::statement("ALTER TABLE crm_leads ADD CONSTRAINT chk_crm_leads_status CHECK (status IN ('new','contacted','qualified','converted','disqualified'))");

        // ── Contacts ─────────────────────────────────────────────────────────
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('position')->nullable();
            $table->string('role', 30)->default('end_user');
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE crm_contacts ADD CONSTRAINT chk_crm_contacts_role CHECK (role IN ('decision_maker','technical','procurement','end_user'))");

        // ── Opportunities ────────────────────────────────────────────────────
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('title');
            $table->unsignedBigInteger('expected_value_centavos')->default(0);
            $table->unsignedSmallInteger('probability_pct')->default(0);
            $table->date('expected_close_date')->nullable();
            $table->string('stage', 30)->default('prospecting');
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('loss_reason')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE crm_opportunities ADD CONSTRAINT chk_crm_opportunities_stage CHECK (stage IN ('prospecting','qualification','proposal','negotiation','closed_won','closed_lost'))");
        DB::statement('ALTER TABLE crm_opportunities ADD CONSTRAINT chk_crm_opportunities_probability CHECK (probability_pct >= 0 AND probability_pct <= 100)');

        // ── Activities ───────────────────────────────────────────────────────
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('contactable_type');
            $table->unsignedBigInteger('contactable_id');
            $table->string('type', 30);
            $table->string('subject');
            $table->text('notes')->nullable();
            $table->timestamp('activity_date');
            $table->timestamp('next_action_date')->nullable();
            $table->string('next_action_description')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contactable_type', 'contactable_id']);
        });

        DB::statement("ALTER TABLE crm_activities ADD CONSTRAINT chk_crm_activities_type CHECK (type IN ('call','meeting','email','note','task'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_leads');
    }
};
