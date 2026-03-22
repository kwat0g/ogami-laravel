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
        private readonly int $disposalId,
        private readonly string $assetName,
        private readonly string $disposalMethod,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(AssetDisposal $disposal): self
    {
        return new self(
            disposalId: $disposal->id,
            assetName: $disposal->fixedAsset->name ?? "Asset #{$disposal->fixed_asset_id}",
            disposalMethod: $disposal->disposal_method ?? 'unspecified',
        );
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
                $this->assetName,
                $this->disposalMethod,
            ),
            'action_url' => '/fixed-assets',
            'disposal_id' => $this->disposalId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
