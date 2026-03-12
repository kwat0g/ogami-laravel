<?php

declare(strict_types=1);

namespace App\Notifications\FixedAssets;

use App\Domains\FixedAssets\Models\AssetDisposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class AssetDisposalApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AssetDisposal $disposal,
    ) {
        $this->queue = 'notifications';
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'fixed_assets.disposal_approval',
            'title' => 'Asset Disposal Approval Required',
            'message' => sprintf(
                'Asset disposal request for "%s" (method: %s) requires your approval.',
                $this->disposal->fixedAsset?->name ?? "Asset #{$this->disposal->fixed_asset_id}",
                $this->disposal->disposal_method ?? 'unspecified',
            ),
            'action_url' => '/fixed-assets',
            'disposal_id' => $this->disposal->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
