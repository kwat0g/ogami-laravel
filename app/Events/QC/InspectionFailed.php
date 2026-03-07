<?php

declare(strict_types=1);

namespace App\Events\QC;

use App\Domains\QC\Models\Inspection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an in-process QC inspection records a failed result.
 * Consumed by Production domain to place the linked work order on hold.
 */
final class InspectionFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Inspection $inspection,
    ) {}
}
