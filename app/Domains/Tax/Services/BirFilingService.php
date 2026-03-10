<?php

declare(strict_types=1);

namespace App\Domains\Tax\Services;

use App\Domains\Tax\Models\BirFiling;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * BIR Filing Service — schedule, record, and query Philippine tax form filings.
 *
 * BIR due dates (calendar day of the following month/quarter unless it falls on
 * a holiday/weekend, in which case it rolls to the next banking day):
 *   Monthly forms (1601C, 0619E, 2550M) → due on the 10th (eFPS large) / 25th (manual)
 *   Quarterly forms (1601EQ, 2550Q, 1702Q) → due on the 25th after quarter end
 *   Annual (1702RT) → due 15th of the 4th month after fiscal year end
 *
 * Per this system we default all monthly forms to the 25th and quarterly to the
 * 25th of the month after the quarter ends; callers may supply an explicit
 * `due_date` to override.
 */
final class BirFilingService implements ServiceContract
{
    // Standard due-day for eFPS filers (non-large taxpayer)
    private const DEFAULT_DUE_DAY = 25;

    /** BIR form types recognised by this system. */
    public const FORM_TYPES = [
        '1601C', '0619E', '1601EQ', '2550M', '2550Q',
        '0605', '1702Q', '1702RT', '2307_alpha',
    ];

    /** Monthly forms — filed each month for the prior month's period. */
    private const MONTHLY_FORMS = ['1601C', '0619E', '2550M'];

    /** Quarterly forms — filed once per quarter. */
    private const QUARTERLY_FORMS = ['1601EQ', '2550Q', '1702Q'];

    // ── Scheduling ────────────────────────────────────────────────────────────

    /**
     * Schedule an upcoming filing (creates a 'pending' record).
     * Idempotent — calling twice for the same form+period is a no-op (returns existing).
     */
    public function schedule(array $data, User $actor): BirFiling
    {
        return DB::transaction(function () use ($data, $actor): BirFiling {
            $existing = BirFiling::query()
                ->where('form_type', $data['form_type'])
                ->where('fiscal_period_id', $data['fiscal_period_id'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $dueDate = isset($data['due_date'])
                ? Carbon::parse($data['due_date'])
                : $this->computeDueDate($data['form_type'], $data['fiscal_period_id']);

            return BirFiling::create([
                'form_type'                 => $data['form_type'],
                'fiscal_period_id'          => $data['fiscal_period_id'],
                'due_date'                  => $dueDate,
                'total_tax_due_centavos'    => $data['total_tax_due_centavos'] ?? 0,
                'status'                    => 'pending',
                'notes'                     => $data['notes'] ?? null,
                'created_by_id'             => $actor->id,
            ]);
        });
    }

    /**
     * Record a BIR form as filed. Updates status to 'filed' (or 'late' if
     * filed_date is after due_date), and stores confirmation number.
     */
    public function markFiled(BirFiling $filing, array $data, User $actor): BirFiling
    {
        if (! in_array($filing->status, ['pending', 'amended'], true)) {
            throw new DomainException(
                "Filing cannot be marked as filed from status '{$filing->status}'.",
                'TAX_FILING_INVALID_STATE',
                422
            );
        }

        return DB::transaction(function () use ($filing, $data, $actor): BirFiling {
            $filedDate = Carbon::parse($data['filed_date']);
            $isLate    = $filedDate->isAfter($filing->due_date);

            $filing->update([
                'filed_date'                => $filedDate,
                'confirmation_number'       => $data['confirmation_number'] ?? null,
                'total_tax_due_centavos'    => $data['total_tax_due_centavos'] ?? $filing->total_tax_due_centavos,
                'status'                    => $isLate ? 'late' : 'filed',
                'notes'                     => $data['notes'] ?? $filing->notes,
                'filed_by_id'               => $actor->id,
            ]);

            return $filing->fresh();
        });
    }

    /**
     * Amend a previously filed return.
     */
    public function markAmended(BirFiling $filing, array $data, User $actor): BirFiling
    {
        if ($filing->status !== 'filed' && $filing->status !== 'late') {
            throw new DomainException(
                'Only filed returns can be amended.',
                'TAX_FILING_CANNOT_AMEND',
                422
            );
        }

        return DB::transaction(function () use ($filing, $data, $actor): BirFiling {
            $filing->update([
                'confirmation_number'       => $data['confirmation_number'] ?? $filing->confirmation_number,
                'total_tax_due_centavos'    => $data['total_tax_due_centavos'] ?? $filing->total_tax_due_centavos,
                'status'                    => 'amended',
                'notes'                     => $data['notes'] ?? $filing->notes,
                'filed_by_id'               => $actor->id,
            ]);

            return $filing->fresh();
        });
    }

    // ── Queries ───────────────────────────────────────────────────────────────

    /**
     * Return all overdue pending filings (due date has passed, still pending).
     *
     * @return Collection<int, BirFiling>
     */
    public function getOverdue(): Collection
    {
        return BirFiling::query()
            ->where('status', 'pending')
            ->where('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Return filing calendar for a given fiscal year — one row per form per period.
     * Groups by form_type with upcoming/late status highlighted.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function getCalendar(int $fiscalYear): array
    {
        $filings = BirFiling::query()
            ->with('createdBy', 'filedBy')
            ->whereHas('fiscalPeriod', fn ($q) => $q->whereYear('date_from', $fiscalYear))
            ->orderBy('due_date')
            ->get();

        $calendar = [];
        foreach ($filings as $filing) {
            $calendar[$filing->form_type][] = [
                'ulid'                      => $filing->ulid,
                'fiscal_period_id'          => $filing->fiscal_period_id,
                'due_date'                  => $filing->due_date->toDateString(),
                'filed_date'                => $filing->filed_date?->toDateString(),
                'confirmation_number'       => $filing->confirmation_number,
                'status'                    => $filing->status,
                'is_overdue'                => $filing->isOverdue(),
                'total_tax_due_centavos'    => $filing->total_tax_due_centavos,
            ];
        }

        return $calendar;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Compute the default BIR due date for a form type given its fiscal period.
     * This is a best-effort approximation; the actual due date depends on the
     * taxpayer classification and BIR revenue regulations.
     */
    private function computeDueDate(string $formType, int $fiscalPeriodId): Carbon
    {
        $period = \App\Domains\Accounting\Models\FiscalPeriod::findOrFail($fiscalPeriodId);
        /** @var Carbon $periodEnd */
        $periodEnd = Carbon::parse($period->date_to);

        return match (true) {
            in_array($formType, self::MONTHLY_FORMS, true)   => $periodEnd->addMonthNoOverflow()->day(self::DEFAULT_DUE_DAY),
            in_array($formType, self::QUARTERLY_FORMS, true) => $periodEnd->addMonthNoOverflow()->day(self::DEFAULT_DUE_DAY),
            $formType === '1702RT'                            => $periodEnd->addMonths(4)->day(15),
            default                                           => $periodEnd->addMonthNoOverflow()->day(self::DEFAULT_DUE_DAY),
        };
    }
}
