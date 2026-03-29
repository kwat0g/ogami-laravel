<?php

declare(strict_types=1);

namespace App\Listeners\Procurement;

use App\Events\Procurement\GoodsReceiptQcCompleted;
use App\Events\Procurement\GoodsReceiptSubmittedForQc;
use App\Models\User;
use App\Notifications\Procurement\GrQcFailedNotification;
use App\Notifications\Procurement\GrSubmittedForQcNotification;
use Illuminate\Support\Facades\Log;

/**
 * Sends notifications when GR QC events occur.
 *
 * - GoodsReceiptSubmittedForQc -> notify QC team (users with qc.inspection.create permission)
 * - GoodsReceiptQcCompleted (failed) -> notify warehouse/procurement staff
 */
class NotifyOnGrQcEvents
{
    public function handleSubmittedForQc(GoodsReceiptSubmittedForQc $event): void
    {
        try {
            $gr = $event->goodsReceipt;
            $notification = GrSubmittedForQcNotification::fromModel($gr);

            // Notify users who can create QC inspections
            User::permission('qc.inspection.create')
                ->each(fn (User $u) => $u->notify($notification));
        } catch (\Throwable $e) {
            Log::warning('[GR-QC] Failed to send QC submission notification', [
                'gr_id' => $event->goodsReceipt->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleQcCompleted(GoodsReceiptQcCompleted $event): void
    {
        if ($event->result !== 'failed') {
            return;
        }

        try {
            $gr = $event->goodsReceipt;
            $notification = GrQcFailedNotification::fromModel($gr);

            // Notify warehouse and procurement staff
            User::permission('procurement.goods-receipt.confirm')
                ->each(fn (User $u) => $u->notify($notification));
        } catch (\Throwable $e) {
            Log::warning('[GR-QC] Failed to send QC failure notification', [
                'gr_id' => $event->goodsReceipt->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
