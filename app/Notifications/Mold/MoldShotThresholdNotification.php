<?php

declare(strict_types=1);

namespace App\Notifications\Mold;

use App\Domains\Mold\Models\MoldMaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class MoldShotThresholdNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly MoldMaster $mold,
        private readonly float $percentage,
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
        $critical = $this->percentage >= 100;

        return [
            'type' => 'mold.shot_threshold',
            'title' => $critical ? '⚠️ Mold Shot Count EXCEEDED' : 'Mold Shot Count Warning',
            'message' => sprintf(
                'Mold %s has reached %.1f%% of max shot count. %s',
                $this->mold->mold_code ?? "MOLD-{$this->mold->id}",
                $this->percentage,
                $critical ? 'Immediate maintenance required.' : 'Schedule maintenance soon.',
            ),
            'action_url' => "/mold/{$this->mold->ulid}",
            'mold_id' => $this->mold->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
