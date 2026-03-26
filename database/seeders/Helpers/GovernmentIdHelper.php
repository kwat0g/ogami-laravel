<?php

declare(strict_types=1);

namespace Database\Seeders\Helpers;

use App\Domains\HR\Models\Employee;

/**
 * Helper class for seeding government IDs and bank details for employees.
 *
 * This helper generates realistic Philippine government ID numbers and
 * assigns them using the model's encryption-aware setters.
 */
class GovernmentIdHelper
{
    private static int $sssCounter = 1000000000;

    private static int $tinCounter = 100000000;

    private static int $philhealthCounter = 100000000000;

    private static int $pagibigCounter = 100000000000;

    /**
     * Complete government ID data including encrypted fields and hashes.
     *
     * @return array<string, mixed>
     */
    public static function generateCompleteGovIds(): array
    {
        // Generate unique base numbers
        $sssNo = self::generateSssNo();
        $tin = self::generateTin();
        $philhealthNo = self::generatePhilhealthNo();
        $pagibigNo = self::generatePagibigNo();

        return [
            'sss_no' => $sssNo,
            'tin' => $tin,
            'philhealth_no' => $philhealthNo,
            'pagibig_no' => $pagibigNo,
            'sss_no_encrypted' => encrypt($sssNo),
            'sss_no_hash' => hash('sha256', self::normalizeGovId($sssNo)),
            'tin_encrypted' => encrypt($tin),
            'tin_hash' => hash('sha256', self::normalizeGovId($tin)),
            'philhealth_no_encrypted' => encrypt($philhealthNo),
            'philhealth_no_hash' => hash('sha256', self::normalizeGovId($philhealthNo)),
            'pagibig_no_encrypted' => encrypt($pagibigNo),
            'pagibig_no_hash' => hash('sha256', self::normalizeGovId($pagibigNo)),
        ];
    }

    /**
     * Generate bank details for an employee.
     *
     * @return array<string, string|null>
     */
    public static function generateBankDetails(string $firstName, string $lastName): array
    {
        $banks = ['BDO', 'BPI', 'Metrobank', 'UnionBank', 'ChinaBank', 'Security Bank', 'PNB', 'RCBC'];
        $bankName = $banks[array_rand($banks)];

        return [
            'bank_name' => $bankName,
            'bank_account_number' => self::generateBankAccountNo(),
            'bank_account_name' => "{$firstName} {$lastName}",
        ];
    }

    /**
     * Assign government IDs to an employee using model methods.
     */
    public static function assignToEmployee(Employee $employee): void
    {
        $govIds = self::generateCompleteGovIds();

        $employee->setSssNo($govIds['sss_no']);
        $employee->setTin($govIds['tin']);
        $employee->setPhilhealthNo($govIds['philhealth_no']);
        $employee->setPagibigNo($govIds['pagibig_no']);
    }

    /**
     * Generate a valid SSS number (10 digits, format: XX-XXXXXXX-X).
     * Range: 01-9999999-9
     */
    public static function generateSssNo(): string
    {
        self::$sssCounter++;
        $num = self::$sssCounter;
        $prefix = str_pad((string) random_int(1, 99), 2, '0', STR_PAD_LEFT);
        $middle = str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        $suffix = random_int(0, 9);

        return "{$prefix}-{$middle}-{$suffix}";
    }

    /**
     * Generate a valid TIN (9-12 digits, format: XXX-XXX-XXX-XXX).
     * Philippine TIN format
     */
    public static function generateTin(): string
    {
        self::$tinCounter++;
        $part1 = str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
        $part2 = str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
        $part3 = str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
        $part4 = str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

        return "{$part1}-{$part2}-{$part3}-{$part4}";
    }

    /**
     * Generate a valid PhilHealth number (12 digits, format: XX-XXXXXXXXX-X).
     */
    public static function generatePhilhealthNo(): string
    {
        self::$philhealthCounter++;
        $prefix = str_pad((string) random_int(1, 99), 2, '0', STR_PAD_LEFT);
        $middle = str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        $suffix = random_int(0, 9);

        return "{$prefix}-{$middle}-{$suffix}";
    }

    /**
     * Generate a valid Pag-IBIG number (12 digits).
     * Format: XXXX-XXXX-XXXX or just 12 digits
     */
    public static function generatePagibigNo(): string
    {
        self::$pagibigCounter++;
        $part1 = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $part2 = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $part3 = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

        return "{$part1}-{$part2}-{$part3}";
    }

    /**
     * Generate a bank account number (10-16 digits).
     */
    public static function generateBankAccountNo(): string
    {
        $length = random_int(10, 16);
        $account = '';
        for ($i = 0; $i < $length; $i++) {
            $account .= random_int(0, 9);
        }

        return $account;
    }

    /**
     * Normalize government ID for hashing.
     */
    private static function normalizeGovId(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $value));
    }
}
