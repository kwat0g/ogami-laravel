<?php

declare(strict_types=1);

namespace App\Notifications\Recruitment;

use App\Domains\HR\Recruitment\Models\Hiring;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class HiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $hiringId,
        private readonly string $candidateName,
        private readonly string $positionTitle,
        private readonly string $departmentName,
        private readonly string $startDate,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(Hiring $hiring): self
    {
        return new self(
            hiringId: $hiring->id,
            candidateName: $hiring->application?->candidate?->full_name ?? 'A candidate',
            positionTitle: $hiring->application?->offer?->offeredPosition?->title ?? 'a position',
            departmentName: $hiring->application?->offer?->offeredDepartment?->name ?? 'a department',
            startDate: (string) $hiring->start_date,
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
            'type' => 'recruitment.hired',
            'title' => 'New Hire',
            'message' => sprintf(
                '%s has been hired as %s in %s, starting %s.',
                $this->candidateName,
                $this->positionTitle,
                $this->departmentName,
                $this->startDate,
            ),
            'hiring_id' => $this->hiringId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
