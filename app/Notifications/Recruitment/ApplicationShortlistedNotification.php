<?php

declare(strict_types=1);

namespace App\Notifications\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ApplicationShortlistedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $applicationId,
        private readonly string $candidateName,
        private readonly string $positionTitle,
        private readonly string $companyName,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(Application $app): self
    {
        return new self(
            applicationId: $app->id,
            candidateName: $app->candidate?->full_name ?? 'Candidate',
            positionTitle: $app->posting?->title ?? 'the position',
            companyName: config('app.name', 'Ogami Manufacturing Corp.'),
        );
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("You've been shortlisted - {$this->positionTitle}")
            ->greeting("Dear {$this->candidateName},")
            ->line("We are pleased to inform you that you have been shortlisted for the position of {$this->positionTitle} at {$this->companyName}.")
            ->line('Our recruitment team will be in touch shortly to schedule the next steps.')
            ->line('Thank you for your interest in joining our team.');
    }
}
