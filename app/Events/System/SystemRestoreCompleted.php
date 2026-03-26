<?php

declare(strict_types=1);

namespace App\Events\System;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on the public `system` channel after a DB restore finishes.
 * All connected front-end clients receive this and are immediately redirected
 * to /login so they pick up the restored data on their next session.
 */
final class SystemRestoreCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $filename,
    ) {}

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['filename' => $this->filename];
    }

    public function broadcastAs(): string
    {
        return 'system.restore.completed';
    }

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        return [new Channel('system')];
    }
}
