<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * F-013: Centralized document number generation service.
 *
 * Generates unique, sequential document numbers with configurable format.
 * Uses PostgreSQL advisory locks to prevent concurrent duplicate numbers.
 *
 * Format: {prefix}-{year}{separator}{sequence}
 * Example: PO-2026-00001, GR-2026-00042, PR-2026-00003
 *
 * Configuration is stored in the document_number_configs table.
 * If no config exists for a document type, a default format is used.
 */
final class DocumentNumberService implements ServiceContract
{
    /**
     * Generate the next document number for a given document type.
     *
     * @param string $documentType e.g. 'purchase_order', 'goods_receipt', 'sales_order'
     * @param string|null $prefix Override prefix (if null, uses config or default)
     * @param int|null $fiscalYear Override fiscal year (if null, uses current year)
     *
     * @return string The generated document number
     *
     * @throws DomainException If number generation fails
     */
    public function generate(string $documentType, ?string $prefix = null, ?int $fiscalYear = null): string
    {
        $year = $fiscalYear ?? (int) now()->format('Y');

        // Load config from document_number_configs if available
        $config = DB::table('document_number_configs')
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->first();

        $resolvedPrefix = $prefix ?? $config->prefix ?? $this->defaultPrefix($documentType);
        $separator = $config->separator ?? '-';
        $padding = $config->zero_padding ?? 5;
        $resetOnFiscalYear = $config->reset_on_fiscal_year ?? true;

        return DB::transaction(function () use ($documentType, $year, $resolvedPrefix, $separator, $padding, $resetOnFiscalYear): string {
            // Use PostgreSQL advisory lock to prevent concurrent access
            // Hash the document_type + year to a bigint for the lock key
            $lockKey = crc32($documentType . ($resetOnFiscalYear ? ":{$year}" : ''));
            DB::statement("SELECT pg_advisory_xact_lock({$lockKey})");

            // Get current sequence value
            $currentSeq = DB::table('document_number_sequences')
                ->where('document_type', $documentType)
                ->where('fiscal_year', $resetOnFiscalYear ? $year : 0)
                ->lockForUpdate()
                ->value('last_number');

            $nextSeq = ($currentSeq ?? 0) + 1;

            // Upsert the sequence
            DB::table('document_number_sequences')->updateOrInsert(
                [
                    'document_type' => $documentType,
                    'fiscal_year' => $resetOnFiscalYear ? $year : 0,
                ],
                [
                    'last_number' => $nextSeq,
                    'updated_at' => now(),
                ],
            );

            $paddedSeq = str_pad((string) $nextSeq, $padding, '0', STR_PAD_LEFT);

            return $resetOnFiscalYear
                ? "{$resolvedPrefix}{$separator}{$year}{$separator}{$paddedSeq}"
                : "{$resolvedPrefix}{$separator}{$paddedSeq}";
        });
    }

    /**
     * Default prefix mapping for document types.
     */
    private function defaultPrefix(string $documentType): string
    {
        return match ($documentType) {
            'job_requisition' => 'REQ',
            'job_posting' => 'JP',
            'job_application' => 'APP',
            'job_offer' => 'OFR',
            'purchase_request' => 'PR',
            'purchase_order' => 'PO',
            'goods_receipt' => 'GR',
            'sales_order' => 'SO',
            'quotation' => 'QT',
            'delivery_receipt' => 'DR',
            'customer_invoice' => 'INV',
            'vendor_invoice' => 'VINV',
            'journal_entry' => 'JE',
            'production_order' => 'PROD',
            'material_requisition' => 'MRQ',
            'leave_request' => 'LR',
            'loan' => 'LN',
            'maintenance_work_order' => 'WO',
            'payroll_run' => 'PAY',
            default => strtoupper(substr($documentType, 0, 4)),
        };
    }
}
