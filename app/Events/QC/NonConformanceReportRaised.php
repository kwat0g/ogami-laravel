<?php

declare(strict_types=1);

namespace App\Events\QC;

use App\Domains\QC\Models\NonConformanceReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Non-Conformance Report is raised.
 * Consumed by the QC domain to auto-create a draft CAPA Action
 * so QC staff can track and resolve every non-conformance.
 */
final class NonConformanceReportRaised
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly NonConformanceReport $ncr,
    ) {}
}
