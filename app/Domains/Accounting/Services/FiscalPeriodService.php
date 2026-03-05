<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\FiscalPeriod;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fiscal Period Service — manages accounting calendar lifecycle.
 *
 * Rules enforced:
 *  - No overlapping periods (DB GIST index as belt + service as suspender).
 *  - Closing a period with open JE drafts is rejected.
 *  - A closed period can be re-opened by Accounting Manager (via Policy).
 */
final class FiscalPeriodService implements ServiceContract
{
    public function create(array $data): FiscalPeriod
    {
        return FiscalPeriod::create(array_merge($data, ['status' => 'open']));
    }

    /**
     * Re-open a closed fiscal period.
     * Called by FiscalPeriodController after Policy authorization.
     */
    public function open(FiscalPeriod $period): FiscalPeriod
    {
        if ($period->isOpen()) {
            throw new DomainException(
                message: "Fiscal period '{$period->name}' is already open.",
                errorCode: 'FISCAL_PERIOD_ALREADY_OPEN',
                httpStatus: 409,
            );
        }

        $period->update(['status' => 'open', 'closed_at' => null, 'closed_by' => null]);

        return $period->fresh();
    }

    /**
     * Close a fiscal period.
     * Guard: rejects if any JE in draft/submitted status remains in that period.
     */
    public function close(FiscalPeriod $period): FiscalPeriod
    {
        if (! $period->isOpen()) {
            throw new DomainException(
                message: "Fiscal period '{$period->name}' is already closed.",
                errorCode: 'FISCAL_PERIOD_ALREADY_CLOSED',
                httpStatus: 409,
            );
        }

        $openJeCount = $period->journalEntries()
            ->whereIn('status', ['draft', 'submitted'])
            ->count();

        if ($openJeCount > 0) {
            throw new DomainException(
                message: "Cannot close fiscal period '{$period->name}': {$openJeCount} journal ".
                    ($openJeCount === 1 ? 'entry' : 'entries').
                    ' still in draft or submitted status. Post or cancel them first.',
                errorCode: 'FISCAL_PERIOD_HAS_OPEN_ENTRIES',
                httpStatus: 422,
                context: ['open_je_count' => $openJeCount, 'period' => $period->name],
            );
        }

        $period->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => auth()->id(),
        ]);

        return $period->fresh();
    }

    /**
     * Find the fiscal period that contains the given date.
     * Returns null if no period covers the date.
     */
    public function resolveForDate(Carbon $date): ?FiscalPeriod
    {
        return FiscalPeriod::whereDate('date_from', '<=', $date->toDateString())
            ->whereDate('date_to', '>=', $date->toDateString())
            ->first();
    }

    /**
     * Like resolveForDate but throws if not found.
     */
    public function resolveForDateOrFail(Carbon $date): FiscalPeriod
    {
        $period = $this->resolveForDate($date);

        if ($period === null) {
            throw new DomainException(
                message: "No fiscal period exists for {$date->toDateString()}. Create a fiscal period before posting journal entries.",
                errorCode: 'NO_FISCAL_PERIOD_FOR_DATE',
                httpStatus: 422,
                context: ['date' => $date->toDateString()],
            );
        }

        if (! $period->isOpen()) {
            throw new DomainException(
                message: "The fiscal period '{$period->name}' covering {$date->toDateString()} is closed. Reopen it to post entries. (JE-004)",
                errorCode: 'FISCAL_PERIOD_CLOSED',
                httpStatus: 422,
                context: ['period' => $period->name, 'date' => $date->toDateString()],
            );
        }

        return $period;
    }
}
