<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OT multiplier configurations — all 9 DOLE scenarios, effective-date versioned.
 *
 * EARN-004: OT multiplier is always loaded from this table.
 * Never hardcode $otMultiplier = 1.25 anywhere in the codebase.
 *
 * The 9 scenarios (seeded in OvertimeMultiplierSeeder):
 *   1. REGULAR_DAY_OT          1.25× (Labor Code Art. 87)
 *   2. REST_DAY_WORK           1.30× (Art. 93a)
 *   3. REST_DAY_OT             1.30× × 1.30× = 1.69× (Art. 93c) — base already factored in seeder
 *   4. SPECIAL_HOLIDAY_WORK    1.30× (Art. 94/RA 9492)
 *   5. SPECIAL_HOLIDAY_OT      1.30× × 1.30× = 1.69×
 *   6. SPECIAL_HOLIDAY_REST    1.50×
 *   7. SPECIAL_HOLIDAY_REST_OT 1.50× × 1.30× = 1.95×
 *   8. REGULAR_HOLIDAY_WORK    2.00× (Art. 94)
 *   9. REGULAR_HOLIDAY_OT      2.00× × 1.30× = 2.60×
 *  10. REST_DAY_REGULAR_HOL    2.60× (EDGE-006)
 *  11. REST_DAY_REGULAR_HOL_OT 2.60× × 1.30× = 3.38×
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_multiplier_configs', function (Blueprint $table): void {
            $table->id();
            $table->date('effective_date');
            $table->string('scenario', 60)->comment('Uppercase snake_case scenario identifier, e.g. REGULAR_DAY_OT');
            $table->decimal('multiplier', 8, 4)->comment('Applied to hourly rate × hours worked for this scenario');
            $table->text('dole_legal_basis')->nullable()->comment('Labor Code article or RA reference');
            $table->text('description')->nullable()->comment('Human-readable description for the settings UI');
            $table->timestamps();
        });

        DB::statement('
            ALTER TABLE overtime_multiplier_configs
            ADD CONSTRAINT chk_ot_multiplier_gte_one CHECK (multiplier >= 1)
        ');

        DB::statement('
            CREATE UNIQUE INDEX idx_ot_multiplier_scenario_date
            ON overtime_multiplier_configs (scenario, effective_date DESC)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_multiplier_configs');
    }
};
