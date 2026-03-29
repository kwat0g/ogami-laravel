<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Recruitment\Enums\HiringStatus;
use App\Domains\HR\Recruitment\Enums\OfferStatus;
use App\Domains\HR\Recruitment\Enums\PreEmploymentStatus;
use App\Domains\HR\Recruitment\Enums\RequisitionStatus;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\Hiring;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class HiringService implements ServiceContract
{
    public function hire(Application $application, array $data, User $actor): Hiring
    {
        // Validate offer is accepted
        $offer = $application->offer;
        if (! $offer || $offer->status !== OfferStatus::Accepted) {
            throw new DomainException(
                'Cannot hire without an accepted offer.',
                'NO_ACCEPTED_OFFER',
                422,
                ['application_id' => $application->id],
            );
        }

        // Validate pre-employment is complete or waived
        $checklist = $application->preEmploymentChecklist;
        if ($checklist && ! in_array($checklist->status, [PreEmploymentStatus::Completed, PreEmploymentStatus::Waived])) {
            throw new DomainException(
                'Pre-employment requirements are not yet completed.',
                'PREEMPLOYMENT_INCOMPLETE',
                422,
                ['checklist_status' => $checklist->status->value],
            );
        }

        return DB::transaction(function () use ($application, $data, $actor, $offer): Hiring {
            $requisition = $application->posting->requisition;
            $candidate = $application->candidate;

            $year = date('Y');
            $last = Employee::where('employee_code', 'LIKE', "EMP-{$year}-%")
                ->orderByDesc('employee_code')
                ->value('employee_code');
            $next = $last ? (int) substr($last, -6) + 1 : 1;
            $employeeCode = sprintf('EMP-%s-%06d', $year, $next);

            // Create Employee record with draft/pre-onboarding status
            $employee = Employee::create([
                'employee_code' => $employeeCode,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'personal_email' => $candidate->email,
                'personal_phone' => $candidate->phone,
                'present_address' => $candidate->address,
                'department_id' => $offer->offered_department_id,
                'position_id' => $offer->offered_position_id,
                'employment_type' => $offer->employment_type->value,
                'employment_status' => 'active',
                'onboarding_status' => 'documents_pending',
                'basic_monthly_rate' => $offer->offered_salary,
                'date_hired' => $data['start_date'],
                'date_of_birth' => $data['date_of_birth'] ?? '1990-01-01',
                'gender' => $data['gender'] ?? 'other',
                'civil_status' => $data['civil_status'] ?? 'SINGLE',
                'bir_status' => $data['bir_status'] ?? 'S',
                'pay_basis' => 'monthly',
                'is_active' => false,
            ]);

            $hiring = Hiring::create([
                'application_id' => $application->id,
                'job_requisition_id' => $requisition->id,
                'employee_id' => $employee->id,
                'status' => HiringStatus::Hired->value,
                'hired_at' => now(),
                'start_date' => $data['start_date'],
                'hired_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            // Close requisition if headcount is fulfilled
            if ($requisition->isHeadcountFulfilled()) {
                $requisition->status = RequisitionStatus::Closed;
                $requisition->save();
                $requisition->logApproval('closed', 'processed', $actor, 'Headcount fulfilled');
            }

            return $hiring;
        });
    }
}
