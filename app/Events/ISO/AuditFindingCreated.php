<?php

declare(strict_types=1);

namespace App\Events\ISO;

use App\Domains\ISO\Models\AuditFinding;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a new ISO Internal Audit Finding is recorded.
 * Consumed by the QC domain to auto-create a CAPA action for major/minor findings.
 *
 * ISO-QC-001: Significant findings must be linked to a CAPA for resolution tracking.
 */
final class AuditFindingCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AuditFinding $finding,
    ) {}
}
