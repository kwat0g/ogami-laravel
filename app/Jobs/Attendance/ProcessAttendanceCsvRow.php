<?php

declare(strict_types=1);

namespace App\Jobs\Attendance;

use App\Domains\Attendance\Services\AttendanceProcessingService;
use App\Domains\HR\Models\Employee;
use App\Shared\Exceptions\DomainException;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued job that processes a single CSV row from the attendance import.
 *
 * Uses Batchable to allow progress tracking + failure collection via Bus::batch().
 * On failure, the row error is logged but the batch continues (no batch cancellation).
 */
final class ProcessAttendanceCsvRow implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Maximum queue retry attempts */
    public int $tries = 3;

    /** @var int Backoff seconds between retries */
    public int $backoff = 5;

    /**
     * @param  array<string, mixed>  $row  Raw CSV row: { employee_code, work_date, time_in, time_out, source }
     * @param  int  $lineNumber  CSV line number for error reporting
     */
    public function __construct(
        public readonly array $row,
        public readonly int $lineNumber,
    ) {}

    public function handle(AttendanceProcessingService $service): void
    {
        // Skip if the batch has been cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        $employee = Employee::where('employee_code', $this->row['employee_code'] ?? '')
            ->first();

        if ($employee === null) {
            Log::warning('AttendanceImport: Employee not found', [
                'line' => $this->lineNumber,
                'employee_code' => $this->row['employee_code'] ?? 'N/A',
            ]);

            return;
        }

        try {
            $service->processLog(
                $employee,
                $this->row['work_date'],
                [
                    'time_in' => $this->row['time_in'] ?? null,
                    'time_out' => $this->row['time_out'] ?? null,
                    'source' => $this->row['source'] ?? 'csv_import',
                    'raw_line' => $this->lineNumber,
                ],
            );
        } catch (DomainException $e) {
            // Log domain errors per row but do not fail the whole batch
            Log::error('AttendanceImport: DomainException on row', [
                'line' => $this->lineNumber,
                'row' => $this->row,
                'error' => $e->getMessage(),
                'code' => $e->errorCode,
            ]);
        }
    }
}
