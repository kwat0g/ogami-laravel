<?php

declare(strict_types=1);

namespace App\Notifications\Recruitment;

use App\Domains\HR\Recruitment\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ApplicationRejectedNotification extends Notification implements ShouldQueue
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
            ->subject("Application Update - {$this->positionTitle}")
            ->greeting("Dear {$this->candidateName},")
            ->line("Thank you for your interest in the position of {$this->positionTitle} at {$this->companyName}.")
            ->line('After careful consideration, we have decided to move forward with other candidates whose qualifications more closely match our current needs.')
            ->line('We appreciate the time you invested in the application process and encourage you to apply for future openings.');
    }
}
