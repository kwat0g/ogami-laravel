<?php

declare(strict_types=1);

namespace App\Events\Procurement;

use App\Domains\Procurement\Models\GoodsReceipt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Goods Receipt is submitted for incoming quality control.
 *
 * Consumed by:
 *   - CreateIqcInspectionOnGrSubmit (auto-creates IQC inspections for items requiring IQC)
 *   - GrSubmittedForQcNotification (notifies QC team)
 */
final class GoodsReceiptSubmittedForQc
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly GoodsReceipt $goodsReceipt,
    ) {}
}
