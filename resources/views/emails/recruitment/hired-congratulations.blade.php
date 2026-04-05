@component('mail::message')
# Congratulations, {{ $candidateName }}!

We are delighted to inform you that you have been selected for the position of **{{ $positionTitle }}** in the **{{ $departmentName }}** department.

@component('mail::table')
| Detail | Information |
|:-------|:------------|
| **Position** | {{ $positionTitle }} |
| **Department** | {{ $departmentName }} |
| **Start Date** | {{ $startDate }} |
@if($employeeCode)
| **Employee Code** | {{ $employeeCode }} |
@endif
@endcomponent

## Next Steps

Our HR team will be in touch with you shortly regarding onboarding details, including any documents you may need to prepare before your start date.

If you have any questions in the meantime, please do not hesitate to reach out.

Welcome to the team!

Best regards,<br>
{{ config('app.name') }} HR Team
@endcomponent
