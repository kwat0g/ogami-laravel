<?php

declare(strict_types=1);

namespace App\Events\QC;

use App\Domains\QC\Models\Inspection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an in-process QC inspection records all-conforming results.
 * Consumed by Production domain to resume a work order that was placed on hold
 * pending QC clearance (e.g. after an earlier InspectionFailed event).
 *
 * QC-002: Passed in-process inspection unblocks further production.
 */
final class InspectionPassed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Inspection $inspection,
    ) {}
}
