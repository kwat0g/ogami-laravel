<?php

declare(strict_types=1);

namespace App\Mail\Recruitment;

use App\Domains\HR\Recruitment\Models\InterviewSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class InterviewScheduledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $candidateName,
        public readonly string $positionTitle,
        public readonly string $interviewType,
        public readonly string $scheduledAt,
        public readonly int $durationMinutes,
        public readonly ?string $location,
        public readonly int $round,
        public readonly ?string $interviewerName,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(InterviewSchedule $interview): self
    {
        $interview->loadMissing([
            'application.candidate',
            'application.posting.requisition.position',
            'application.posting.position',
            'interviewer',
        ]);

        return new self(
            candidateName: $interview->application?->candidate?->full_name ?? 'Applicant',
            positionTitle: $interview->application?->posting?->requisition?->position?->title
                ?? $interview->application?->posting?->position?->title
                ?? 'the position',
            interviewType: $interview->type?->label() ?? 'Interview',
            scheduledAt: $interview->scheduled_at?->format('l, F j, Y \\a\\t g:i A') ?? '',
            durationMinutes: $interview->duration_minutes ?? 60,
            location: $interview->location,
            round: $interview->round,
            interviewerName: $interview->interviewer?->name,
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Interview Scheduled - {$this->positionTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.recruitment.interview-scheduled',
        );
    }
}
