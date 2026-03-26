<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Align leave types to the physical Leave of Absence Request Form (AD-084-00).
 *
 * Approved types: VL, ML, BDAY, BL, PL, OTH
 * Removed types:  SL, SIL, SPL, VAWCL, LWOP (set inactive)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Deactivate leave types not on the approved form (AD-084-00)
        DB::table('leave_types')
            ->whereNotIn('code', ['VL', 'ML', 'BDAY', 'BL', 'PL', 'OTH'])
            ->update(['is_active' => false]);

        // Add OTH (Others — employee specifies reason) if not already present
        DB::table('leave_types')->insertOrIgnore([[
            'code' => 'OTH',
            'name' => 'Others',
            'category' => 'other',
            'is_paid' => false,   // GA Officer decides pay status at processing step
            'max_days_per_year' => 0,        // discretionary — no fixed entitlement
            'requires_approval' => true,
            'requires_documentation' => false,
            'monthly_accrual_days' => null,
            'max_carry_over_days' => 0,
            'can_be_monetized' => false,
            'deducts_absent_on_lwop' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);
    }

    public function down(): void
    {
        // Reactivate previously active types
        DB::table('leave_types')
            ->whereIn('code', ['SL', 'SIL', 'SPL', 'VAWCL', 'LWOP'])
            ->update(['is_active' => true]);

        // Remove leave_balances referencing OTH before deleting it (FK constraint)
        $othId = DB::table('leave_types')->where('code', 'OTH')->value('id');
        if ($othId) {
            DB::table('leave_balances')->where('leave_type_id', $othId)->delete();
        }

        // Remove OTH
        DB::table('leave_types')->where('code', 'OTH')->delete();
    }
};
