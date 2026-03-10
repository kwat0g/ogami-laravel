<?php

declare(strict_types=1);

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\RecurringJournalTemplate;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Manages recurring JE templates and materialises them into real JEs.
 *
 * GL-REC-001: Create/update templates with line blueprints.
 * GL-REC-002: `generateDueEntries()` called by `journals:generate-recurring`.
 */
final class RecurringJournalTemplateService implements ServiceContract
{
    public function __construct(private readonly JournalEntryService $jeService) {}

    /**
     * @param  array{description: string, frequency: string, day_of_month?: int|null, next_run_date: string, lines: array<int, array<string, mixed>>}  $data
     */
    public function store(array $data, User $actor): RecurringJournalTemplate
    {
        $this->assertLinesBalanced($data['lines']);

        return DB::transaction(function () use ($data, $actor): RecurringJournalTemplate {
            return RecurringJournalTemplate::create([
                'description'   => $data['description'],
                'frequency'     => $data['frequency'],
                'day_of_month'  => $data['day_of_month'] ?? null,
                'next_run_date' => $data['next_run_date'],
                'is_active'     => true,
                'lines'         => $data['lines'],
                'created_by_id' => $actor->id,
            ]);
        });
    }

    /**
     * @param  array{description?: string, frequency?: string, day_of_month?: int|null, next_run_date?: string, lines?: array<int, array<string, mixed>>}  $data
     */
    public function update(RecurringJournalTemplate $template, array $data): RecurringJournalTemplate
    {
        if (isset($data['lines'])) {
            $this->assertLinesBalanced($data['lines']);
        }

        return DB::transaction(function () use ($template, $data): RecurringJournalTemplate {
            $template->update(array_filter([
                'description'   => $data['description'] ?? null,
                'frequency'     => $data['frequency'] ?? null,
                'day_of_month'  => array_key_exists('day_of_month', $data) ? $data['day_of_month'] : null,
                'next_run_date' => $data['next_run_date'] ?? null,
                'lines'         => $data['lines'] ?? null,
            ], fn ($v) => $v !== null));

            return $template->refresh();
        });
    }

    public function toggle(RecurringJournalTemplate $template): RecurringJournalTemplate
    {
        $template->update(['is_active' => ! $template->is_active]);

        return $template->refresh();
    }

    /**
     * Materialise all active templates whose next_run_date is today or in the past.
     *
     * Each materialised template generates one draft JE that awaits normal
     * submission → approval → posting workflow.
     *
     * @return int  Number of JEs created
     */
    public function generateDueEntries(): int
    {
        $templates = RecurringJournalTemplate::where('is_active', true)
            ->whereDate('next_run_date', '<=', now()->toDateString())
            ->get();

        $count = 0;

        foreach ($templates as $template) {
            try {
                $this->materialize($template);
                $count++;
            } catch (\Throwable $e) {
                Log::error('Failed to generate recurring JE', [
                    'template_id'  => $template->id,
                    'template_ulid' => $template->ulid,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Create one JE from the template and advance next_run_date.
     */
    private function materialize(RecurringJournalTemplate $template): void
    {
        DB::transaction(function () use ($template): void {
            $today = now()->toDateString();

            $this->jeService->create([
                'date'        => $today,
                'description' => $template->description . ' (auto ' . $today . ')',
                'source_type' => 'recurring',
                'source_id'   => $template->id,
                'lines'       => $template->lines,
            ]);

            $template->update([
                'last_run_at'   => now(),
                'next_run_date' => $this->advanceDate($template),
            ]);
        });
    }

    private function advanceDate(RecurringJournalTemplate $template): string
    {
        $base = Carbon::parse($template->next_run_date);

        return match ($template->frequency) {
            'daily'       => $base->addDay()->toDateString(),
            'weekly'      => $base->addWeek()->toDateString(),
            'monthly'     => $base->addMonthNoOverflow()->toDateString(),
            'semi_monthly' => $base->addDays(15)->toDateString(),
            'annual'      => $base->addYearNoOverflow()->toDateString(),
            default       => $base->addMonthNoOverflow()->toDateString(),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function assertLinesBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw new DomainException('A recurring template must have at least 2 lines.', 'RJT_MIN_LINES', 422);
        }

        $totalDebit  = array_sum(array_column($lines, 'debit'));
        $totalCredit = array_sum(array_column($lines, 'credit'));

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new DomainException(
                'Template lines are not balanced. Debit total must equal credit total.',
                'RJT_UNBALANCED',
                422,
            );
        }
    }
}
