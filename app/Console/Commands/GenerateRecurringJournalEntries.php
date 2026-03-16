<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Accounting\Services\RecurringJournalTemplateService;
use Illuminate\Console\Command;

/**
 * GL-REC-002 — Materialise due recurring journal entry templates.
 *
 * Designed to be run daily (or more frequently) via the scheduler.
 * Each template whose `next_run_date` <= today will generate one draft JE
 * and advance `next_run_date` by the configured frequency.
 */
final class GenerateRecurringJournalEntries extends Command
{
    protected $signature = 'journals:generate-recurring';

    protected $description = 'Generate journal entries from active recurring templates that are due today';

    public function __construct(private readonly RecurringJournalTemplateService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->service->generateDueEntries();

        if ($count === 0) {
            $this->info('No recurring journal entries due today.');
        } else {
            $this->info("Generated {$count} recurring journal entr".($count === 1 ? 'y' : 'ies').'.');
        }

        return self::SUCCESS;
    }
}
