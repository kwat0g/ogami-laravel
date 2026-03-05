<?php

declare(strict_types=1);

namespace App\Rules\Accounting;

use App\Domains\Accounting\Models\ChartOfAccount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * COA-002: Only leaf-node accounts (no children) may be posted to.
 * An account with existing children rejects this rule.
 */
final class LeafAccountRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return; // let `exists` rule handle that
        }

        $hasChildren = ChartOfAccount::where('parent_id', $value)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasChildren) {
            $fail('The selected account has sub-accounts and cannot be posted to directly. Use a leaf-level account. (COA-002)');
        }
    }
}
