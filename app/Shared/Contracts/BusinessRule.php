<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Every reusable business rule must implement this interface.
 *
 * Rules that implement this contract are individually testable and
 * referenced by rule IDs (e.g., EMP-012, LN-007) in test descriptions.
 *
 * Usage in a Service:
 *   $rule = new MinimumWageRule($salaryType, $region, $date);
 *   if (! $rule->passes($basicSalary)) {
 *       throw new ValidationException($rule->message(), $rule->errorCode());
 *   }
 *
 * Never use anonymous closures for business rules — they cannot be tested
 * in isolation and have no named error code.
 */
interface BusinessRule
{
    /**
     * Run the rule against the given context value.
     *
     * @param  mixed  $context  The value or object being validated.
     */
    public function passes(mixed $context): bool;

    /**
     * Human-readable message returned when the rule fails.
     * Must be specific enough for an HR officer to act on it.
     */
    public function message(): string;

    /**
     * Machine-readable error code in SCREAMING_SNAKE_CASE.
     * The frontend handles errors by code, never by parsing message strings.
     */
    public function errorCode(): string;
}
