<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Services\GoodsReceiptService;
use App\Models\User;

/**
 * Test helper: runs a GR through the mandatory QC flow before confirmation.
 *
 * Replaces direct `$grService->confirm($gr, $actor)` calls in tests that
 * were written before QC became mandatory (draft -> pending_qc -> qc_passed -> confirmed).
 */
final class GrQcTestHelper
{
    /**
     * Submit a draft GR for QC, auto-pass it, then confirm.
     * Returns the confirmed GR.
     */
    public static function submitQcAndConfirm(GoodsReceipt $gr, User $actor, ?GoodsReceiptService $service = null): GoodsReceipt
    {
        $service ??= app(GoodsReceiptService::class);

        // Step 1: Submit for QC (draft -> pending_qc)
        $gr = $service->submitForQc($gr->load('items'), $actor);

        // Step 2: Mark QC passed (pending_qc -> qc_passed)
        $gr = $service->markQcPassed($gr, $actor);

        // Step 3: Confirm (qc_passed -> confirmed)
        return $service->confirm($gr->load('items'), $actor);
    }
}
