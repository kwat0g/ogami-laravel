<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal Entry Template - Pre-defined templates for common journal entries
 */
class JournalEntryTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'created_by_id',
        'template_lines',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'template_lines' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeUserTemplates($query)
    {
        return $query->where('is_system', false);
    }

    // Helper methods
    public function isSystem(): bool
    {
        return $this->is_system;
    }

    /**
     * Validate that all accounts in template still exist
     */
    public function validateAccounts(): array
    {
        $validAccounts = [];
        $invalidAccounts = [];

        foreach ($this->template_lines as $line) {
            $account = ChartOfAccount::find($line['account_id']);
            if ($account && $account->is_active) {
                $validAccounts[] = [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'account_code' => $account->code,
                    'debit_or_credit' => $line['debit_or_credit'],
                    'description' => $line['description'] ?? null,
                ];
            } else {
                $invalidAccounts[] = [
                    'account_id' => $line['account_id'],
                    'debit_or_credit' => $line['debit_or_credit'],
                    'reason' => $account ? 'Account inactive' : 'Account not found',
                ];
            }
        }

        return [
            'valid' => $validAccounts,
            'invalid' => $invalidAccounts,
            'is_valid' => empty($invalidAccounts),
        ];
    }

    /**
     * Apply template to create journal entry lines (amounts to be filled by user)
     */
    public function apply(): array
    {
        $validation = $this->validateAccounts();
        
        if (!$validation['is_valid']) {
            throw new \RuntimeException(
                'Template has invalid accounts: ' . 
                implode(', ', array_column($validation['invalid'], 'account_id'))
            );
        }

        return array_map(function ($account) {
            return [
                'account_id' => $account['account_id'],
                'account_name' => $account['account_name'],
                'account_code' => $account['account_code'],
                'debit_or_credit' => $account['debit_or_credit'],
                'description' => $account['description'],
                'amount' => '', // User fills this
            ];
        }, $validation['valid']);
    }
}
