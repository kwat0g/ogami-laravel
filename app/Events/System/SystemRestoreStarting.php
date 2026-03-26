<?php

declare(strict_types=1);

namespace App\Events\System;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on the public `system` channel immediately (synchronous, no queue)
 * before a database restore wipes the DB and all sessions.
 *
 * All connected front-end clients receive this and show a blocking overlay so
 * users are not confused when their session is suddenly invalidated.
 */
final class SystemRestoreStarting implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $filename,
        public readonly string $initiatedBy,
    ) {}

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'filename' => $this->filename,
            'initiated_by' => $this->initiatedBy,
        ];
    }

    public function broadcastAs(): string
    {
        return 'system.restore.starting';
    }

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        // Public channel — no auth required. Every connected client receives it.
        return [new Channel('system')];
    }
}
