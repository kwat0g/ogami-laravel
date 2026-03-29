<?php

declare(strict_types=1);

namespace App\Notifications\Recruitment;

use App\Domains\HR\Recruitment\Models\InterviewSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class InterviewCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $interviewId,
        private readonly string $candidateName,
        private readonly string $positionTitle,
        private readonly string $scheduledAt,
        private readonly int $round,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(InterviewSchedule $interview): self
    {
        return new self(
            interviewId: $interview->id,
            candidateName: $interview->application?->candidate?->full_name ?? 'A candidate',
            positionTitle: $interview->application?->posting?->requisition?->position?->title ?? 'a position',
            scheduledAt: $interview->scheduled_at?->toFormattedDateString() ?? '',
            round: $interview->round,
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
            'type' => 'recruitment.interview.cancelled',
            'title' => 'Interview Cancelled',
            'message' => sprintf(
                'Interview Round %d for %s (%s) on %s has been cancelled.',
                $this->round, $this->candidateName, $this->positionTitle, $this->scheduledAt,
            ),
            'interview_id' => $this->interviewId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
