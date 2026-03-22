<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->comment('Template name');
            $table->text('description')->nullable()->comment('Template description');
            $table->foreignId('created_by_id')->constrained('users');
            
            // Template lines stored as JSON array
            // [{account_id, debit_or_credit: 'debit'|'credit', description}, ...]
            $table->json('template_lines')->comment('JE line templates');
            
            // System templates are pre-defined, user templates are custom
            $table->boolean('is_system')->default(false)->comment('True for pre-defined templates');
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['is_system', 'is_active']);
            $table->index('created_by_id');
        });
        
        // Seed system templates
        $this->seedSystemTemplates();
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_templates');
    }
    
    private function seedSystemTemplates(): void
    {
        // Get default expense and payable accounts (these should exist from seeders)
        $salariesAccount = \App\Domains\Accounting\Models\ChartOfAccount::where('code', '6100')->first();
        $sssPayable = \App\Domains\Accounting\Models\ChartOfAccount::where('code', '2105')->first();
        $philhealthPayable = \App\Domains\Accounting\Models\ChartOfAccount::where('code', '2106')->first();
        $pagibigPayable = \App\Domains\Accounting\Models\ChartOfAccount::where('code', '2107')->first();
        $withholdingTaxPayable = \App\Domains\Accounting\Models\ChartOfAccount::where('code', '2108')->first();
        $cashAccount = \App\Domains\Accounting\Models\ChartOfAccount::where('code', '1100')->first();
        $depreciationExpense = \App\Domains\Accounting\Models\ChartOfAccount::where('code', '6500')->first();
        $accumulatedDepreciation = \App\Domains\Accounting\Models\ChartOfAccount::where('code', '1501')->first();
        $loanPayable = \App\Domains\Accounting\Models\ChartOfAccount::where('code', '2200')->first();
        $apAccount = \App\Domains\Accounting\Models\ChartOfAccount::where('code', '2000')->first();
        
        $systemUser = \App\Models\User::first();
        
        $templates = [];
        
        // 1. Payroll Accrual Template
        if ($salariesAccount && $cashAccount) {
            $lines = [
                ['account_id' => $salariesAccount->id, 'debit_or_credit' => 'debit', 'description' => 'Salaries and wages'],
            ];
            if ($sssPayable) $lines[] = ['account_id' => $sssPayable->id, 'debit_or_credit' => 'credit', 'description' => 'SSS contributions payable'];
            if ($philhealthPayable) $lines[] = ['account_id' => $philhealthPayable->id, 'debit_or_credit' => 'credit', 'description' => 'PhilHealth contributions payable'];
            if ($pagibigPayable) $lines[] = ['account_id' => $pagibigPayable->id, 'debit_or_credit' => 'credit', 'description' => 'Pag-IBIG contributions payable'];
            if ($withholdingTaxPayable) $lines[] = ['account_id' => $withholdingTaxPayable->id, 'debit_or_credit' => 'credit', 'description' => 'Withholding tax payable'];
            $lines[] = ['account_id' => $cashAccount->id, 'debit_or_credit' => 'credit', 'description' => 'Net pay disbursement'];
            
            $templates[] = [
                'name' => 'Payroll Accrual',
                'description' => 'Standard payroll entry with salary expense and deductions',
                'template_lines' => json_encode($lines),
                'is_system' => true,
                'created_by_id' => $systemUser?->id ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // 2. Monthly Depreciation Template
        if ($depreciationExpense && $accumulatedDepreciation) {
            $templates[] = [
                'name' => 'Monthly Depreciation',
                'description' => 'Record monthly depreciation expense',
                'template_lines' => json_encode([
                    ['account_id' => $depreciationExpense->id, 'debit_or_credit' => 'debit', 'description' => 'Depreciation expense'],
                    ['account_id' => $accumulatedDepreciation->id, 'debit_or_credit' => 'credit', 'description' => 'Accumulated depreciation'],
                ]),
                'is_system' => true,
                'created_by_id' => $systemUser?->id ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // 3. Loan Proceeds Template
        if ($cashAccount && $loanPayable) {
            $templates[] = [
                'name' => 'Loan Proceeds',
                'description' => 'Record receipt of loan funds',
                'template_lines' => json_encode([
                    ['account_id' => $cashAccount->id, 'debit_or_credit' => 'debit', 'description' => 'Cash received'],
                    ['account_id' => $loanPayable->id, 'debit_or_credit' => 'credit', 'description' => 'Loan payable'],
                ]),
                'is_system' => true,
                'created_by_id' => $systemUser?->id ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // 4. Asset Purchase Template
        if ($apAccount) {
            // Find a fixed asset account
            $assetAccount = \App\Domains\Accounting\Models\ChartOfAccount::where('code', 'like', '15%')->first();
            if ($assetAccount) {
                $templates[] = [
                    'name' => 'Asset Purchase on Credit',
                    'description' => 'Purchase of fixed assets with AP',
                    'template_lines' => json_encode([
                        ['account_id' => $assetAccount->id, 'debit_or_credit' => 'debit', 'description' => 'Fixed asset acquired'],
                        ['account_id' => $apAccount->id, 'debit_or_credit' => 'credit', 'description' => 'Accounts payable'],
                    ]),
                    'is_system' => true,
                    'created_by_id' => $systemUser?->id ?? 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        
        if (!empty($templates)) {
            DB::table('journal_entry_templates')->insert($templates);
        }
    }
};
