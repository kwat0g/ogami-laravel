<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Philippine holiday calendar per year.
 *
 * ATT-009: Holiday pay eligibility is determined exclusively by this table.
 * EARN-007/EARN-008: Holiday type (Regular/Special) comes from here — never
 * inferred from the holiday name or hardcoded as a date constant.
 *
 * An Admin populates this table at the start of each year via the Settings UI.
 * A worker job can auto-import from the Official Gazette PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_calendars', function (Blueprint $table): void {
            $table->id();
            $table->date('holiday_date')->unique()->comment('The specific calendar date of the holiday');
            $table->smallInteger('year')->comment('Denormalized year for fast filtering');
            $table->string('name', 150)->comment('Official holiday name, e.g. "Araw ng Kagitingan"');
            $table->string('type', 30)->comment('REGULAR | SPECIAL_NON_WORKING | SPECIAL_WORKING');
            $table->boolean('is_nationwide')->default(true);
            $table->string('region', 20)->nullable()->comment('If regional holiday, region code; otherwise NULL');
            $table->text('proclamation_reference')->nullable();
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE holiday_calendars
            ADD CONSTRAINT chk_holiday_type_valid
            CHECK (type IN ('REGULAR', 'SPECIAL_NON_WORKING', 'SPECIAL_WORKING'))
        ");

        DB::statement('
            CREATE INDEX idx_holiday_calendars_year
            ON holiday_calendars (year, holiday_date)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_calendars');
    }
};
