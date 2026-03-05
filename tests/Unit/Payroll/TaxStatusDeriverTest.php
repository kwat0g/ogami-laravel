<?php

declare(strict_types=1);

use App\Domains\Payroll\Services\TaxStatusDeriver;

/*
|--------------------------------------------------------------------------
| TaxStatusDeriver — Unit Tests
|--------------------------------------------------------------------------
| Verifies TRAIN Law civil_status + dependent count → BIR tax code mapping.
|
| BIR codes covered:
|   S           — Single / Legally Separated / Widowed, any dep count
|   ME / ME1–4  — Married Employee, 0–4 qualified dependents
|   HF / HF1–4  — Head of Family (single parent), 0–4 qualified dependents
--------------------------------------------------------------------------
*/

describe('TaxStatusDeriver — Single status', function () {
    it('derives S for single with 0 dependents', function () {
        expect(TaxStatusDeriver::derive('single', 0))->toBe('S');
    });

    it('derives S for single with dependents (single has no ME1–4 / HF equivalent)', function () {
        // Per TRAIN Law, only ME and HF codes have numbered dependent suffixes.
        expect(TaxStatusDeriver::derive('single', 3))->toBe('S');
    });

    it('derives S for legally_separated with 0 dependents', function () {
        expect(TaxStatusDeriver::derive('legally_separated', 0))->toBe('S');
    });

    it('derives S for separated (alias) with 0 dependents', function () {
        expect(TaxStatusDeriver::derive('separated', 0))->toBe('S');
    });

    it('derives S for widowed with 0 dependents', function () {
        expect(TaxStatusDeriver::derive('widowed', 0))->toBe('S');
    });

    it('derives S for widow (singular alias) with 0 dependents', function () {
        expect(TaxStatusDeriver::derive('widow', 0))->toBe('S');
    });
});

describe('TaxStatusDeriver — Married status', function () {
    it('derives ME for married with 0 dependents', function () {
        expect(TaxStatusDeriver::derive('married', 0))->toBe('ME');
    });

    it('derives ME1 for married with 1 dependent', function () {
        expect(TaxStatusDeriver::derive('married', 1))->toBe('ME1');
    });

    it('derives ME4 for married with 4 dependents (maximum)', function () {
        expect(TaxStatusDeriver::derive('married', 4))->toBe('ME4');
    });

    it('clamps to ME4 when dependent count exceeds 4 (BIR max)', function () {
        expect(TaxStatusDeriver::derive('married', 10))->toBe('ME4');
        expect(TaxStatusDeriver::derive('married', 99))->toBe('ME4');
    });
});

describe('TaxStatusDeriver — Head of Family', function () {
    it('derives HF for head_of_family with 0 dependents', function () {
        expect(TaxStatusDeriver::derive('head_of_family', 0))->toBe('HF');
    });

    it('derives HF2 for head_of_family with 2 dependents', function () {
        expect(TaxStatusDeriver::derive('head_of_family', 2))->toBe('HF2');
    });

    it('derives HF4 for head_of_family with 4 dependents', function () {
        expect(TaxStatusDeriver::derive('head_of_family', 4))->toBe('HF4');
    });
});

describe('TaxStatusDeriver — Edge cases', function () {
    it('is case-insensitive for civil_status', function () {
        expect(TaxStatusDeriver::derive('MARRIED', 1))->toBe('ME1');
        expect(TaxStatusDeriver::derive('Single', 0))->toBe('S');
        expect(TaxStatusDeriver::derive('HEAD_OF_FAMILY', 0))->toBe('HF');
    });

    it('trims whitespace from civil_status', function () {
        expect(TaxStatusDeriver::derive('  married  ', 0))->toBe('ME');
    });

    it('clamps negative dependent count to 0 (returns base code)', function () {
        expect(TaxStatusDeriver::derive('married', -1))->toBe('ME');
        expect(TaxStatusDeriver::derive('head_of_family', -5))->toBe('HF');
    });

    it('defaults unknown civil_status to S (conservative BIR fallback — lowest bracket)', function () {
        expect(TaxStatusDeriver::derive('unknown_status', 0))->toBe('S');
        expect(TaxStatusDeriver::derive('', 0))->toBe('S');
    });
});
