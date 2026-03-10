<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed Assets domain — asset categories, asset register, depreciation entries, and disposals.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Asset Categories ─────────────────────────────────────────────────
        Schema::create('fixed_asset_categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name', 100)->unique();
            $table->string('code_prefix', 10)->comment('Used in auto-generated asset codes, e.g. BLDG, VEH');
            $table->unsignedTinyInteger('default_useful_life_years')->default(5);
            $table->string('default_depreciation_method', 30)->default('straight_line');
            // Optional GL account links — if set, depreciation auto-posts a JE
            $table->foreignId('gl_asset_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('gl_depreciation_expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('gl_accumulated_depreciation_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("
            ALTER TABLE fixed_asset_categories
            ADD CONSTRAINT chk_fac_depreciation_method
            CHECK (default_depreciation_method IN ('straight_line','double_declining','units_of_production'))
        ");

        // ── Asset Register ───────────────────────────────────────────────────
        Schema::create('fixed_assets', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('asset_code', 40)->unique();
            $table->foreignId('category_id')->constrained('fixed_asset_categories');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('location', 200)->nullable();
            $table->date('acquisition_date');
            $table->unsignedBigInteger('acquisition_cost_centavos')->comment('₱xx,xxx = xx_xxx_00 centavos');
            $table->unsignedBigInteger('residual_value_centavos')->default(0);
            $table->unsignedTinyInteger('useful_life_years');
            $table->string('depreciation_method', 30)->default('straight_line');
            $table->unsignedBigInteger('accumulated_depreciation_centavos')->default(0);
            $table->string('status', 30)->default('active');
            $table->string('purchase_invoice_ref', 100)->nullable();
            $table->string('purchased_from', 200)->nullable();
            $table->date('disposal_date')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id']);
        });

        DB::statement("
            ALTER TABLE fixed_assets
            ADD CONSTRAINT chk_fa_status
            CHECK (status IN ('active','fully_depreciated','disposed','impaired'))
        ");

        DB::statement("
            ALTER TABLE fixed_assets
            ADD CONSTRAINT chk_fa_depreciation_method
            CHECK (depreciation_method IN ('straight_line','double_declining','units_of_production'))
        ");

        DB::statement("
            ALTER TABLE fixed_assets
            ADD CONSTRAINT chk_fa_residual_lte_cost
            CHECK (residual_value_centavos <= acquisition_cost_centavos)
        ");

        // Auto-generate asset code: {prefix}-YYYY-NNN
        DB::statement("CREATE SEQUENCE IF NOT EXISTS fixed_asset_seq START 1");

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_fixed_asset_code()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            DECLARE
                prefix TEXT;
            BEGIN
                SELECT code_prefix INTO prefix
                FROM fixed_asset_categories WHERE id = NEW.category_id;
                NEW.asset_code := prefix || '-' || TO_CHAR(NOW(), 'YYYY') || '-' ||
                                  LPAD(NEXTVAL('fixed_asset_seq')::TEXT, 4, '0');
                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_fixed_asset_code
            BEFORE INSERT ON fixed_assets
            FOR EACH ROW WHEN (NEW.asset_code IS NULL OR NEW.asset_code = '')
            EXECUTE FUNCTION fn_fixed_asset_code()
        SQL);

        // ── Depreciation Entries ─────────────────────────────────────────────
        Schema::create('fixed_asset_depreciation_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->foreignId('fiscal_period_id')->constrained('fiscal_periods');
            $table->unsignedBigInteger('depreciation_amount_centavos');
            $table->string('method', 30);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('computed_by_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['fixed_asset_id', 'fiscal_period_id'], 'uq_fa_dep_period');
        });

        // ── Disposals ────────────────────────────────────────────────────────
        Schema::create('fixed_asset_disposals', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('disposal_date');
            $table->unsignedBigInteger('proceeds_centavos')->default(0);
            $table->string('disposal_method', 30)->default('write_off');
            $table->bigInteger('gain_loss_centavos')->default(0)->comment('Positive = gain; negative = loss');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("
            ALTER TABLE fixed_asset_disposals
            ADD CONSTRAINT chk_fad_method
            CHECK (disposal_method IN ('sale','scrap','donation','write_off'))
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_fixed_asset_code ON fixed_assets');
        DB::statement('DROP FUNCTION IF EXISTS fn_fixed_asset_code()');
        DB::statement('DROP SEQUENCE IF EXISTS fixed_asset_seq');

        Schema::dropIfExists('fixed_asset_disposals');
        Schema::dropIfExists('fixed_asset_depreciation_entries');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('fixed_asset_categories');
    }
};
