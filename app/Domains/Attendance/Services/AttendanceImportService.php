<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Jobs\Attendance\ProcessAttendanceCsvRow;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use SplFileObject;

/**
 * Attendance CSV Import Service.
 *
 * Parses a CSV file and dispatches each row as a queued job via Laravel Horizon.
 * Implements ATT-001 through ATT-006 indirectly via AttendanceProcessingService.
 *
 * Expected CSV format (header row required):
 *   employee_code, work_date, time_in, time_out, source
 *
 * - employee_code: e.g. EMP-2025-000001
 * - work_date: Y-m-d format
 * - time_in: H:i or H:i:s (nullable)
 * - time_out: H:i or H:i:s (nullable)
 * - source: biometric|manual|csv_import (optional, defaults to csv_import)
 *
 * Processing is done via Laravel Bus::batch() so progress can be tracked
 * and partial failures are collected without cancelling the entire batch.
 */
final class AttendanceImportService implements ServiceContract
{
    /**
     * Required CSV headers (order-independent).
     *
     * @var list<string>
     */
    private const REQUIRED_HEADERS = ['employee_code', 'work_date'];

    /**
     * Import attendance from an uploaded CSV file path (relative to storage).
     *
     * @param  string  $storagePath  Path relative to the default disk (e.g. 'imports/att_2025.csv')
     * @param  int  $uploadedByUserId  User who initiated the import (for audit)
     * @return array<string, mixed> { batch_id, total_rows, dispatched_rows, skipped_rows, errors[] }
     *
     * @throws DomainException
     */
    public function importFromStorage(string $storagePath, int $uploadedByUserId): array
    {
        $absolutePath = Storage::path($storagePath);

        if (! file_exists($absolutePath)) {
            throw new DomainException(
                "Import file not found: {$storagePath}",
                'ATT_IMPORT_FILE_NOT_FOUND',
                404,
            );
        }

        return $this->processFile($absolutePath, $uploadedByUserId);
    }

    /**
     * Import attendance from an absolute filesystem path.
     * Used for direct uploads in controllers (after request->file->store()).
     *
     * @return array<string, mixed>
     *
     * @throws DomainException
     */
    public function importFromPath(string $absolutePath, int $uploadedByUserId): array
    {
        if (! file_exists($absolutePath)) {
            throw new DomainException(
                'Import file not found.',
                'ATT_IMPORT_FILE_NOT_FOUND',
                404,
            );
        }

        return $this->processFile($absolutePath, $uploadedByUserId);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Parse CSV, validate headers, dispatch row jobs, return batch metadata.
     *
     * @return array<string, mixed>
     *
     * @throws DomainException
     */
    private function processFile(string $absolutePath, int $uploadedByUserId): array
    {
        $file = new SplFileObject($absolutePath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        // Read and validate header row
        /** @var list<string>|false $headerRow */
        $headerRow = $file->current();
        if ($headerRow === false || empty($headerRow)) {
            throw new DomainException('CSV file is empty or has no header row.', 'ATT_IMPORT_EMPTY', 422);
        }

        $headers = array_map('trim', array_map('strtolower', $headerRow));
        $this->validateHeaders($headers);

        $file->next(); // Advance past header

        $jobs = [];
        $lineNumber = 2; // Data starts at line 2
        $skipped = 0;
        $parseErrors = [];

        while (! $file->eof()) {
            /** @var list<string>|false $rawRow */
            $rawRow = $file->current();
            $file->next();

            if ($rawRow === false || count(array_filter($rawRow)) === 0) {
                continue; // skip blank lines
            }

            // Map header positions to named values
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($rawRow[$index]) ? trim($rawRow[$index]) : '';
            }

            // Basic row-level validation
            $error = $this->validateRow($row, $lineNumber);
            if ($error !== null) {
                $parseErrors[] = $error;
                $skipped++;
                $lineNumber++;

                continue;
            }

            $jobs[] = new ProcessAttendanceCsvRow($row, $lineNumber);
            $lineNumber++;
        }

        if (empty($jobs)) {
            return [
                'batch_id' => null,
                'total_rows' => $lineNumber - 2,
                'dispatched_rows' => 0,
                'skipped_rows' => $skipped,
                'errors' => $parseErrors,
            ];
        }

        // Dispatch batch via Horizon
        $totalDispatched = count($jobs);
        $batch = Bus::batch($jobs)
            ->name("attendance-import-{$uploadedByUserId}-".date('YmdHis'))
            ->allowFailures()  // ATT: row failures don't cancel batch
            ->onQueue('attendance')
            ->dispatch();

        return [
            'batch_id' => $batch->id,
            'total_rows' => $lineNumber - 2,
            'dispatched_rows' => $totalDispatched,
            'skipped_rows' => $skipped,
            'errors' => $parseErrors,
        ];
    }

    /**
     * Validate CSV headers contain required columns.
     *
     * @param  list<string>  $headers
     *
     * @throws DomainException
     */
    private function validateHeaders(array $headers): void
    {
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);

        if (! empty($missing)) {
            throw new DomainException(
                sprintf(
                    'CSV is missing required columns: %s. Got: %s',
                    implode(', ', $missing),
                    implode(', ', $headers),
                ),
                'ATT_IMPORT_MISSING_HEADERS',
                422,
            );
        }
    }

    /**
     * Validate a single CSV row.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>|null null if valid, error array if invalid
     */
    private function validateRow(array $row, int $lineNumber): ?array
    {
        if (empty($row['employee_code'])) {
            return ['line' => $lineNumber, 'error' => 'employee_code is required', 'row' => $row];
        }

        if (empty($row['work_date'])) {
            return ['line' => $lineNumber, 'error' => 'work_date is required', 'row' => $row];
        }

        // Validate date format Y-m-d
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['work_date'])) {
            return ['line' => $lineNumber, 'error' => "work_date '{$row['work_date']}' is not in Y-m-d format", 'row' => $row];
        }

        // Validate time formats if provided (HH:MM or HH:MM:SS)
        foreach (['time_in', 'time_out'] as $timeField) {
            if (! empty($row[$timeField]) && ! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $row[$timeField])) {
                return ['line' => $lineNumber, 'error' => "{$timeField} '{$row[$timeField]}' is not a valid time", 'row' => $row];
            }
        }

        return null;
    }
}
