<?php

declare(strict_types=1);

namespace App\Events\Procurement;

use App\Domains\Procurement\Models\GoodsReceipt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when QC inspection result is recorded for a Goods Receipt.
 *
 * Consumed by notification listeners to alert warehouse/procurement teams.
 */
final class GoodsReceiptQcCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly GoodsReceipt $goodsReceipt,
        public readonly string $result, // 'passed' | 'failed' | 'partial'
    ) {}
}
