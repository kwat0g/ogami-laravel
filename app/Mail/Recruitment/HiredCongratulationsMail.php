<?php

declare(strict_types=1);

namespace App\Mail\Recruitment;

use App\Domains\HR\Recruitment\Models\Hiring;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class HiredCongratulationsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $candidateName,
        public readonly string $positionTitle,
        public readonly string $departmentName,
        public readonly string $startDate,
        public readonly ?string $employeeCode,
    ) {
        $this->queue = 'notifications';
    }

    public static function fromModel(Hiring $hiring): self
    {
        $hiring->loadMissing([
            'application.candidate',
            'application.offer.offeredPosition',
            'application.offer.offeredDepartment',
            'application.posting.requisition.position',
            'application.posting.requisition.department',
            'application.posting.position',
            'application.posting.department',
            'employee',
        ]);

        $positionTitle = $hiring->application?->offer?->offeredPosition?->title
            ?? $hiring->application?->posting?->requisition?->position?->title
            ?? $hiring->application?->posting?->position?->title
            ?? 'the position';

        $departmentName = $hiring->application?->offer?->offeredDepartment?->name
            ?? $hiring->application?->posting?->requisition?->department?->name
            ?? $hiring->application?->posting?->department?->name
            ?? 'the department';

        return new self(
            candidateName: $hiring->application?->candidate?->full_name ?? 'Applicant',
            positionTitle: $positionTitle,
            departmentName: $departmentName,
            startDate: (string) $hiring->start_date,
            employeeCode: $hiring->employee?->employee_code,
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Ogami - You Have Been Hired!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.recruitment.hired-congratulations',
        );
    }
}
