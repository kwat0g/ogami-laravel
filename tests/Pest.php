<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case Base Configuration
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Integration');

// Unit/Shared — pure value-object tests, no database required
pest()->extend(TestCase::class)
    ->in('Unit/Shared');

// E2E — full sequential walkthrough tests; each file brings its own DB trait
pest()->extend(TestCase::class)
    ->in('E2E');

// Unit/Payroll — service tests that hit contribution & tax rate tables
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit/Payroll');

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidationError', function (string $field) {
    return $this
        ->toHaveKey('success', false)
        ->toHaveKey('error_code', 'VALIDATION_ERROR')
        ->and($this->value['errors'])->toHaveKey($field);
});

expect()->extend('toBeDomainError', function (string $errorCode, ?int $httpStatus = null) {
    $this->toHaveKey('success', false)
        ->toHaveKey('error_code', $errorCode);

    if ($httpStatus !== null) {
        // Response status is checked separately via $response->assertStatus()
    }

    return $this;
});

/*
|--------------------------------------------------------------------------
| Dataset Helpers
|--------------------------------------------------------------------------
*/

/**
 * Returns a dataset for amount boundary tests (negative, zero, over-limit).
 *
 * @return array<string, array<mixed>>
 */
function invalidAmounts(): array
{
    return [
        'negative' => [-1],
        'zero' => [0],
        'over_max' => [PHP_INT_MAX],
    ];
}
