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
        private readonly int $moldId,
        private readonly string $moldUlid,
        private readonly string $moldCode,
        private readonly float $percentage,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(MoldMaster $mold, float $percentage): self
    {
        return new self(
            moldId: $mold->id,
            moldUlid: $mold->ulid,
            moldCode: $mold->mold_code ?? "MOLD-{$mold->id}",
            percentage: $percentage,
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
        $critical = $this->percentage >= 100;

        return [
            'type' => 'mold.shot_threshold',
            'title' => $critical ? '⚠️ Mold Shot Count EXCEEDED' : 'Mold Shot Count Warning',
            'message' => sprintf(
                'Mold %s has reached %.1f%% of max shot count. %s',
                $this->moldCode,
                $this->percentage,
                $critical ? 'Immediate maintenance required.' : 'Schedule maintenance soon.',
            ),
            'action_url' => "/mold/{$this->moldUlid}",
            'mold_id' => $this->moldId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
