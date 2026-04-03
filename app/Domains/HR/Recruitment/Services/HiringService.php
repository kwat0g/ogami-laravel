<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Recruitment\Enums\ApplicationStatus;
use App\Domains\HR\Recruitment\Enums\HiringStatus;
use App\Domains\HR\Recruitment\Enums\OfferStatus;
use App\Domains\HR\Recruitment\Enums\PreEmploymentStatus;
use App\Domains\HR\Recruitment\Enums\RequisitionStatus;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\Hiring;
use App\Models\User;
use App\Notifications\Recruitment\HiredNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class HiringService implements ServiceContract
{
    public function submitForApproval(Application $application, array $data, User $actor): Hiring
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

        if ($application->hiring !== null) {
            throw new DomainException(
                'Hiring request already exists for this application.',
                'HIRING_ALREADY_EXISTS',
                422,
                ['application_id' => $application->id],
            );
        }

        $hiring = DB::transaction(function () use ($application, $data, $actor, $offer): Hiring {
            $requisition = $application->posting->requisition;

            return Hiring::create([
                'application_id' => $application->id,
                'job_requisition_id' => $requisition->id,
                'employee_id' => null,
                'employee_payload' => $data,
                'status' => HiringStatus::PendingVpApproval->value,
                'hired_at' => null,
                'start_date' => $data['start_date'],
                'hired_by' => $actor->id,
                'submitted_by_id' => $actor->id,
                'submitted_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return $hiring->fresh(['application.candidate', 'application.offer.offeredPosition', 'application.offer.offeredDepartment']);
    }

    public function vpApprove(Hiring $hiring, User $actor, ?string $notes = null): Hiring
    {
        if ($hiring->status !== HiringStatus::PendingVpApproval) {
            throw new DomainException(
                'Only pending VP approval hiring requests can be approved.',
                'HIRING_NOT_PENDING_VP_APPROVAL',
                422,
                ['status' => $hiring->status->value],
            );
        }

        $payload = $hiring->employee_payload;
        if (! is_array($payload) || empty($payload)) {
            throw new DomainException(
                'Employee payload is missing for this hiring request.',
                'HIRING_EMPLOYEE_PAYLOAD_MISSING',
                422,
            );
        }

        $approved = DB::transaction(function () use ($hiring, $payload, $actor, $notes): Hiring {
            $application = $hiring->application()->with(['candidate', 'posting.requisition'])->firstOrFail();

            $year = date('Y');
            $last = Employee::where('employee_code', 'LIKE', "EMP-{$year}-%")
                ->orderByDesc('employee_code')
                ->value('employee_code');
            $next = $last ? (int) substr($last, -6) + 1 : 1;
            $employeeCode = sprintf('EMP-%s-%06d', $year, $next);

            $employee = Employee::create([
                'employee_code' => $employeeCode,
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'],
                'middle_name' => $payload['middle_name'] ?? null,
                'suffix' => $payload['suffix'] ?? null,
                'date_of_birth' => $payload['date_of_birth'],
                'gender' => $payload['gender'],
                'civil_status' => $payload['civil_status'] ?? 'SINGLE',
                'citizenship' => $payload['citizenship'] ?? null,
                'present_address' => $payload['present_address'],
                'permanent_address' => $payload['permanent_address'] ?? null,
                'personal_email' => $payload['personal_email'],
                'personal_phone' => $payload['personal_phone'] ?? null,
                'department_id' => $payload['department_id'],
                'position_id' => $payload['position_id'],
                'salary_grade_id' => $payload['salary_grade_id'] ?? null,
                'reports_to' => $payload['reports_to'] ?? null,
                'employment_type' => $payload['employment_type'],
                'employment_status' => 'active',
                'pay_basis' => $payload['pay_basis'] ?? ($payload['pay_frequency'] ?? 'monthly'),
                'basic_monthly_rate' => (int) ($payload['basic_monthly_rate'] ?? $payload['base_salary_monthly_centavos']),
                'date_hired' => $payload['start_date'],
                'regularization_date' => $payload['regularization_date'] ?? null,
                'onboarding_status' => 'documents_pending',
                'is_active' => false,
                'bir_status' => $payload['bir_status'] ?? 'S',
                'bank_name' => $payload['bank_name'] ?? null,
                'bank_account_no' => $payload['bank_account_no'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);

            $employee
                ->setSssNo($payload['sss_no'] ?? null)
                ->setTin($payload['tin'] ?? null)
                ->setPhilhealthNo($payload['philhealth_no'] ?? null)
                ->setPagibigNo($payload['pagibig_no'] ?? null)
                ->save();

            $hiring->update([
                'employee_id' => $employee->id,
                'status' => HiringStatus::Hired->value,
                'hired_at' => now(),
                'hired_by' => $actor->id,
                'vp_approved_by_id' => $actor->id,
                'vp_approved_at' => now(),
                'notes' => $notes !== null ? trim(($hiring->notes ?? '')."\nVP approval: {$notes}") : $hiring->notes,
            ]);

            $application->status = ApplicationStatus::Hired;
            $application->save();

            $requisition = $application->posting->requisition;
            if ($requisition !== null && $requisition->isHeadcountFulfilled()) {
                $requisition->status = RequisitionStatus::Closed;
                $requisition->save();
                $requisition->logApproval('closed', 'processed', $actor, 'Headcount fulfilled');
            }

            return $hiring->fresh(['application.candidate', 'application.offer.offeredPosition', 'application.offer.offeredDepartment']);
        });

        $hrManagers = User::whereHas('roles', fn ($q) => $q->where('name', 'manager'))
            ->whereHas('departments', fn ($q) => $q->where('code', 'HR'))
            ->get();
        foreach ($hrManagers as $mgr) {
            $mgr->notify(HiredNotification::fromModel($approved));
        }

        return $approved;
    }

    public function vpReject(Hiring $hiring, User $actor, string $reason): Hiring
    {
        if ($hiring->status !== HiringStatus::PendingVpApproval) {
            throw new DomainException(
                'Only pending VP approval hiring requests can be rejected.',
                'HIRING_NOT_PENDING_VP_APPROVAL',
                422,
                ['status' => $hiring->status->value],
            );
        }

        $hiring->update([
            'status' => HiringStatus::RejectedByVp->value,
            'vp_rejected_by_id' => $actor->id,
            'vp_rejected_at' => now(),
            'vp_rejection_reason' => $reason,
        ]);

        return $hiring->fresh(['application.candidate']);
    }

    public function hire(Application $application, array $data, User $actor): Hiring
    {
        return $this->submitForApproval($application, $data, $actor);
    }
}
