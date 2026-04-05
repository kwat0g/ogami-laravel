<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Recruitment\Enums\ApplicationStatus;
use App\Domains\HR\Recruitment\Enums\HiringStatus;
use App\Domains\HR\Recruitment\Enums\PostingStatus;
use App\Domains\HR\Recruitment\Enums\RequisitionStatus;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\Hiring;
use App\Mail\Recruitment\HiredCongratulationsMail;
use App\Models\User;
use App\Notifications\Recruitment\HiredNotification;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class HiringService implements ServiceContract
{
    public function submitForApproval(Application $application, array $data, User $actor): Hiring
    {
        $application->loadMissing([
            'candidate',
            'posting.requisition',
            'posting.requisition.salaryGrade',
            'posting.salaryGrade',
            'posting.department',
            'posting.position',
            'offer',
            'preEmploymentChecklist',
            'hiring',
        ]);

        $this->assertCanProceedWithHiring($application);

        // Offer is optional in the current flow; hiring may proceed with posting/data defaults.
        $offer = $application->offer;

        if ($application->hiring !== null) {
            throw new DomainException(
                'Hiring request already exists for this application.',
                'HIRING_ALREADY_EXISTS',
                422,
                ['application_id' => $application->id],
            );
        }

        $candidate = $application->candidate;
        if ($candidate === null) {
            throw new DomainException(
                'Cannot create hiring request without candidate profile data.',
                'CANDIDATE_PROFILE_MISSING',
                422,
                ['application_id' => $application->id],
            );
        }

        $candidateFirstName = trim((string) ($data['first_name'] ?? $candidate->first_name ?? ''));
        $candidateLastName = trim((string) ($data['last_name'] ?? $candidate->last_name ?? ''));
        if (($candidateFirstName === '' || $candidateLastName === '') && is_string($candidate->full_name)) {
            $nameParts = preg_split('/\s+/', trim($candidate->full_name)) ?: [];
            if ($candidateFirstName === '' && $nameParts !== []) {
                $candidateFirstName = (string) array_shift($nameParts);
            }
            if ($candidateLastName === '' && $nameParts !== []) {
                $candidateLastName = (string) array_pop($nameParts);
            }
        }

        $posting = $application->posting;
        $departmentId = $data['department_id']
            ?? $offer?->offered_department_id
            ?? $posting?->department_id
            ?? $posting?->requisition?->department_id
            ?? null;
        $positionId = $data['position_id']
            ?? $offer?->offered_position_id
            ?? $posting?->position_id
            ?? $posting?->requisition?->position_id
            ?? null;
        $employmentType = $data['employment_type']
            ?? $offer?->employment_type?->value
            ?? $posting?->employment_type?->value
            ?? null;
        $basicMonthlyRate = $data['basic_monthly_rate']
            ?? $offer?->offered_salary
            ?? null;
        $salaryGradeId = $data['salary_grade_id']
            ?? $posting?->salary_grade_id
            ?? $posting?->requisition?->salary_grade_id
            ?? null;
        $resolvedSalaryGrade = $posting?->salaryGrade ?? $posting?->requisition?->salaryGrade;

        if ($basicMonthlyRate === null && $resolvedSalaryGrade !== null) {
            $basicMonthlyRate = (int) $resolvedSalaryGrade->min_monthly_rate;
        }

        if ($departmentId === null || $positionId === null || $employmentType === null || $basicMonthlyRate === null) {
            throw new DomainException(
                'Hiring requires resolved department, position, employment type, and monthly rate.',
                'HIRING_DEFAULTS_NOT_RESOLVED',
                422,
                [
                    'department_id' => $departmentId,
                    'position_id' => $positionId,
                    'employment_type' => $employmentType,
                    'basic_monthly_rate' => $basicMonthlyRate,
                ],
            );
        }

        $normalizedPayload = [
            'start_date' => $data['start_date'],
            'first_name' => $candidateFirstName,
            'last_name' => $candidateLastName,
            'middle_name' => $data['middle_name'] ?? null,
            'suffix' => $data['suffix'] ?? null,
            'date_of_birth' => $data['date_of_birth'],
            'gender' => $data['gender'],
            'civil_status' => $data['civil_status'] ?? 'SINGLE',
            'citizenship' => $data['citizenship'] ?? null,
            'present_address' => $data['present_address'] ?? $candidate->address ?? 'N/A',
            'permanent_address' => $data['permanent_address'] ?? null,
            'personal_email' => $data['personal_email'] ?? $candidate->email,
            'personal_phone' => $data['personal_phone'] ?? $candidate->phone,
            'department_id' => (int) $departmentId,
            'position_id' => (int) $positionId,
            'salary_grade_id' => $salaryGradeId !== null ? (int) $salaryGradeId : null,
            'reports_to' => $data['reports_to'] ?? null,
            'employment_type' => (string) $employmentType,
            'pay_basis' => $data['pay_basis'] ?? 'monthly',
            'basic_monthly_rate' => (int) $basicMonthlyRate,
            'regularization_date' => $data['regularization_date'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account_no' => $data['bank_account_no'] ?? null,
            'sss_no' => $data['sss_no'] ?? null,
            'tin' => $data['tin'] ?? null,
            'philhealth_no' => $data['philhealth_no'] ?? null,
            'pagibig_no' => $data['pagibig_no'] ?? null,
            'notes' => $data['notes'] ?? null,
            'application_ulid' => $application->ulid,
            'application_number' => $application->application_number,
            'candidate_id' => $candidate->id,
            'candidate_full_name' => $candidate->full_name,
            'posting_ulid' => $posting?->ulid,
            'posting_number' => $posting?->posting_number,
            'posting_title' => $posting?->title,
        ];

        $requiredEmployeeFields = [
            'first_name',
            'last_name',
            'date_of_birth',
            'gender',
            'civil_status',
            'present_address',
            'personal_email',
            'department_id',
            'position_id',
            'employment_type',
            'pay_basis',
            'basic_monthly_rate',
        ];

        $missingFields = [];
        foreach ($requiredEmployeeFields as $field) {
            $value = $normalizedPayload[$field] ?? null;

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missingFields[] = $field;
            }
        }

        if ($missingFields !== []) {
            throw new DomainException(
                'Cannot execute hire with incomplete employee data.',
                'HIRING_EMPLOYEE_DATA_INCOMPLETE',
                422,
                ['missing_fields' => $missingFields],
            );
        }

        $hiring = DB::transaction(function () use ($application, $normalizedPayload, $actor): Hiring {
            $requisition = $application->posting->requisition;

            return Hiring::create([
                'application_id' => $application->id,
                'job_requisition_id' => $requisition?->id,
                'employee_id' => null,
                'employee_payload' => $normalizedPayload,
                'status' => HiringStatus::PendingVpApproval->value,
                'hired_at' => null,
                'start_date' => $normalizedPayload['start_date'],
                'hired_by' => $actor->id,
                'submitted_by_id' => $actor->id,
                'submitted_at' => now(),
                'notes' => $normalizedPayload['notes'],
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

            $this->assertCanProceedWithHiring($application, $hiring->id);

            $this->acquireEmployeeCodeLock();

            $employee = null;
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $employeeCode = $this->nextEmployeeCode();

                try {
                    // Keep each create attempt isolated so a unique conflict does not poison
                    // the parent transaction state.
                    $employee = DB::transaction(function () use ($payload, $employeeCode): Employee {
                        return Employee::create([
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
                    });

                    break;
                } catch (QueryException $e) {
                    $isEmployeeCodeConflict = (string) $e->getCode() === '23505'
                        && str_contains($e->getMessage(), 'employees_employee_code_unique');

                    if (! $isEmployeeCodeConflict) {
                        throw $e;
                    }
                }
            }

            if ($employee === null) {
                throw new DomainException(
                    'Unable to generate a unique employee code. Please retry.',
                    'EMPLOYEE_CODE_GENERATION_FAILED',
                    500,
                );
            }

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

                $posting = $application->posting;
                if ($posting->status === PostingStatus::Published) {
                    $posting->status = PostingStatus::Closed;
                    $posting->save();
                }
            }

            return $hiring->fresh(['application.candidate', 'application.offer.offeredPosition', 'application.offer.offeredDepartment']);
        });

        $hrManagers = User::whereHas('roles', fn ($q) => $q->where('name', 'manager'))
            ->whereHas('departments', fn ($q) => $q->where('code', 'HR'))
            ->get();
        foreach ($hrManagers as $mgr) {
            $mgr->notify(HiredNotification::fromModel($approved));
        }

        $candidateEmail = $approved->application?->candidate?->email;
        if ($candidateEmail !== null && $candidateEmail !== '') {
            Mail::to($candidateEmail)->queue(HiredCongratulationsMail::fromModel($approved));
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

    private function nextEmployeeCode(): string
    {
        $year = date('Y');
        $last = Employee::query()
            ->whereRaw("employee_code ~ ?", ["^EMP-{$year}-[0-9]{6}$"])
            ->orderByDesc('employee_code')
            ->value('employee_code');
        $next = $last ? (int) substr($last, -6) + 1 : 1;

        return sprintf('EMP-%s-%06d', $year, $next);
    }

    private function acquireEmployeeCodeLock(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::select("SELECT pg_advisory_xact_lock(hashtext('employees_employee_code'))");
    }

    private function assertCanProceedWithHiring(Application $application, ?int $excludingHiringId = null): void
    {
        $posting = $application->posting;
        if ($posting === null) {
            throw new DomainException(
                'Cannot hire because no job posting is linked to this application.',
                'HIRING_POSTING_MISSING',
                422,
                ['application_id' => $application->id],
            );
        }

        $postingStatus = $posting->status instanceof PostingStatus
            ? $posting->status->value
            : (string) $posting->status;

        if ($postingStatus !== PostingStatus::Published->value) {
            throw new DomainException(
                'Cannot proceed with hiring because the job posting is no longer open.',
                'HIRING_POSTING_NOT_OPEN',
                422,
                [
                    'posting_id' => $posting->id,
                    'posting_status' => $postingStatus,
                ],
            );
        }

        $postingHeadcount = (int) ($posting->headcount ?? $posting->requisition?->headcount ?? 0);
        if ($postingHeadcount <= 0) {
            return;
        }

        $hiredCountForPosting = Hiring::query()
            ->where('status', HiringStatus::Hired->value)
            ->when(
                $excludingHiringId !== null,
                fn ($query) => $query->where('id', '!=', $excludingHiringId),
            )
            ->whereHas('application', fn ($query) => $query->where('job_posting_id', $posting->id))
            ->count();

        if ($hiredCountForPosting >= $postingHeadcount) {
            throw new DomainException(
                'Cannot proceed with hiring because posting headcount is already fulfilled.',
                'HIRING_POSTING_HEADCOUNT_FULFILLED',
                422,
                [
                    'posting_id' => $posting->id,
                    'posting_headcount' => $postingHeadcount,
                    'hired_count' => $hiredCountForPosting,
                ],
            );
        }
    }
}
