<?php

declare(strict_types=1);

namespace App\Listeners\QC;

use App\Domains\QC\Models\CapaAction;
use App\Events\QC\NonConformanceReportRaised;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Auto-create a draft CAPA Action when an NCR is raised.
 *
 * Symmetric to CreateCapaOnAuditFinding for ISO findings.
 * Every NCR automatically gets a corrective action record so QC staff
 * have a structured resolution path without a manual "Issue CAPA" step.
 *
 * The auto-created CAPA has a placeholder description — QC staff must
 * update it with the actual corrective action before marking complete.
 */
final class CreateCapaOnNcrRaised implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue;

    public string $queue = 'default';
    public int $uniqueFor = 60;

    public function uniqueId(NonConformanceReportRaised $event): string
    {
        return 'ncr-capa-' . $event->ncr->id;
    }

    public function handle(NonConformanceReportRaised $event): void
    {
        $ncr = $event->ncr;

        // Idempotency: CAPA already issued for this NCR — skip silently.
        if ($ncr->status === 'capa_issued') {
            return;
        }

        try {
            CapaAction::create([
                'ncr_id'           => $ncr->id,
                'audit_finding_id' => null,
                'type'             => 'corrective',
                'description'      => "Auto-generated from NCR {$ncr->ncr_reference}. Update with specific corrective action details before marking complete.",
                'due_date'         => now()->addDays(14)->toDateString(),
                'status'           => 'open',
                'assigned_to_id'   => null,
                'created_by_id'    => $ncr->raised_by_id,
            ]);

            // Transition NCR to capa_issued (mirrors issueCapa() in NcrService)
            $ncr->update(['status' => 'capa_issued']);
        } catch (\Throwable $e) {
            // Log but do not re-throw — NCR creation must not roll back due to CAPA failure.
            Log::error('Auto CAPA creation failed after NCR raised', [
                'ncr_id'    => $ncr->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
