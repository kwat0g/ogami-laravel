@component('mail::message')
# Interview Scheduled

Dear {{ $candidateName }},

We are pleased to inform you that your interview for the position of **{{ $positionTitle }}** has been scheduled.

@component('mail::table')
| Detail | Information |
|:-------|:------------|
| **Interview Type** | {{ $interviewType }} |
| **Round** | {{ $round }} |
| **Date & Time** | {{ $scheduledAt }} |
| **Duration** | {{ $durationMinutes }} minutes |
@if($location)
| **Location** | {{ $location }} |
@endif
@if($interviewerName)
| **Interviewer** | {{ $interviewerName }} |
@endif
@endcomponent

Please make sure to arrive at least 10 minutes before the scheduled time. If you need to reschedule, please contact us as soon as possible.

We look forward to meeting you.

Best regards,<br>
{{ config('app.name') }} HR Team
@endcomponent
