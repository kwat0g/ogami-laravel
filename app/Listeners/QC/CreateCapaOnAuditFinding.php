<?php

declare(strict_types=1);

namespace App\Listeners\QC;

use App\Domains\QC\Models\CapaAction;
use App\Events\ISO\AuditFindingCreated;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Creates a Corrective Action (CAPA) when a major or minor ISO Audit Finding
 * is raised, ensuring every significant non-conformance has a resolution path.
 *
 * ISO-QC-001: Observations and informational notes do NOT trigger a CAPA.
 */
final class CreateCapaOnAuditFinding implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue;

    public string $queue = 'default';
    public int $uniqueFor = 60;

    public function uniqueId(AuditFindingCreated $event): string
    {
        return 'finding-capa-' . $event->finding->id;
    }

    public function handle(AuditFindingCreated $event): void
    {
        $finding = $event->finding;

        // Only auto-create CAPA for major or minor findings; skip observations
        if (! in_array($finding->severity, ['major', 'minor'], true)) {
            return;
        }

        CapaAction::create([
            'ncr_id'           => null,
            'audit_finding_id' => $finding->id,
            'type'             => $finding->severity === 'major' ? 'corrective' : 'preventive',
            'description'      => "CAPA initiated from ISO Audit Finding #{$finding->id}: {$finding->description}",
            'due_date'         => now()->addDays(30)->toDateString(),
            'status'           => 'open',
            'created_by_id'    => $finding->raised_by_id,
        ]);
    }
}
