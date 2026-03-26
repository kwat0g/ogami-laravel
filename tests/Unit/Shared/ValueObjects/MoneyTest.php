<?php

declare(strict_types=1);

use App\Shared\Exceptions\ValidationException;
use App\Shared\ValueObjects\Money;

/*
|--------------------------------------------------------------------------
| Money Value Object Tests
|--------------------------------------------------------------------------
| Validates: centavo storage, arithmetic, PHP_ROUND_HALF_UP, format output.
--------------------------------------------------------------------------
*/

describe('Money construction', function () {
    it('creates from float and stores centavos', function () {
        $money = Money::fromFloat(15000.00);
        expect($money->centavos)->toBe(1_500_000);
    });

    it('creates from centavos directly', function () {
        $money = Money::fromCentavos(1_500_000);
        expect($money->centavos)->toBe(1_500_000);
    });

    it('creates zero', function () {
        expect(Money::zero()->centavos)->toBe(0);
    });

    it('rejects negative centavos', function () {
        expect(fn () => Money::fromCentavos(-1))
            ->toThrow(ValidationException::class);
    });
});

describe('Money arithmetic', function () {
    it('adds two money values', function () {
        $a = Money::fromFloat(10_000.00);
        $b = Money::fromFloat(5_000.00);
        expect($a->add($b)->centavos)->toBe(1_500_000);
    });

    it('subtracts money values', function () {
        $a = Money::fromFloat(10_000.00);
        $b = Money::fromFloat(3_000.00);
        expect($a->subtract($b)->centavos)->toBe(700_000);
    });

    it('rejects subtraction that would go negative', function () {
        $a = Money::fromFloat(100.00);
        $b = Money::fromFloat(200.00);
        expect(fn () => $a->subtract($b))
            ->toThrow(ValidationException::class);
    });

    it('multiplies using PHP_ROUND_HALF_UP', function () {
        // 1 centavo * 1.505 = 1.505 → rounds to 2 centavos
        $money = Money::fromCentavos(1)->multiply(1.505);
        expect($money->centavos)->toBe(2);
    });

    it('divides correctly', function () {
        $money = Money::fromFloat(10_000.00)->divide(3);
        // 1_000_000 / 3 = 333333.33... → 333_333 centavos
        expect($money->centavos)->toBe(333_333);
    });
});

describe('Money formatting', function () {
    it('formats with peso sign and thousand separator', function () {
        $money = Money::fromFloat(15_000.00);
        expect($money->format())->toBe('₱15,000.00');
    });

    it('formats zero correctly', function () {
        expect(Money::zero()->format())->toBe('₱0.00');
    });

    it('formats large amounts', function () {
        $money = Money::fromFloat(1_234_567.89);
        expect($money->format())->toBe('₱1,234,567.89');
    });
});
