<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Services;

use App\Domains\HR\Recruitment\Enums\PreEmploymentStatus;
use App\Domains\HR\Recruitment\Enums\RequirementStatus;
use App\Domains\HR\Recruitment\Enums\RequirementType;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\PreEmploymentChecklist;
use App\Domains\HR\Recruitment\Models\PreEmploymentRequirement;
use App\Models\User;
use App\Shared\Contracts\ServiceContract;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PreEmploymentService implements ServiceContract
{
    /**
     * Default requirements for pre-employment checklist.
     *
     * @var list<array{type: string, label: string, is_required: bool}>
     */
    private const DEFAULT_REQUIREMENTS = [
        ['type' => 'nbi_clearance', 'label' => 'NBI Clearance', 'is_required' => true],
        ['type' => 'medical_certificate', 'label' => 'Medical Certificate', 'is_required' => true],
        ['type' => 'tin', 'label' => 'TIN (Tax Identification Number)', 'is_required' => true],
        ['type' => 'sss', 'label' => 'SSS Number / ID', 'is_required' => true],
        ['type' => 'philhealth', 'label' => 'PhilHealth Number / ID', 'is_required' => true],
        ['type' => 'pagibig', 'label' => 'Pag-IBIG MID Number', 'is_required' => true],
        ['type' => 'birth_certificate', 'label' => 'Birth Certificate (PSA)', 'is_required' => true],
        ['type' => 'diploma', 'label' => 'Diploma / Certificate of Graduation', 'is_required' => false],
        ['type' => 'transcript', 'label' => 'Transcript of Records', 'is_required' => false],
        ['type' => 'id_photo', 'label' => '2x2 ID Photo (4 pcs)', 'is_required' => true],
    ];

    public function show(Application $application): ?PreEmploymentChecklist
    {
        return $application->preEmploymentChecklist?->load('requirements');
    }

    public function initChecklist(Application $application): PreEmploymentChecklist
    {
        if ($application->preEmploymentChecklist()->exists()) {
            return $application->preEmploymentChecklist->load('requirements');
        }

        return DB::transaction(function () use ($application): PreEmploymentChecklist {
            $checklist = PreEmploymentChecklist::create([
                'application_id' => $application->id,
                'status' => PreEmploymentStatus::Pending->value,
            ]);

            foreach (self::DEFAULT_REQUIREMENTS as $req) {
                $checklist->requirements()->create([
                    'requirement_type' => $req['type'],
                    'label' => $req['label'],
                    'is_required' => $req['is_required'],
                    'status' => RequirementStatus::Pending->value,
                ]);
            }

            return $checklist->load('requirements');
        });
    }

    public function submitDocument(PreEmploymentRequirement $requirement, UploadedFile $file, User $actor): void
    {
        $path = $file->store('recruitment/pre-employment', 'local');

        DB::transaction(function () use ($requirement, $path): void {
            $requirement->update([
                'document_path' => $path,
                'status' => RequirementStatus::Submitted->value,
                'submitted_at' => now(),
            ]);

            $this->updateChecklistStatus($requirement->checklist);
        });
    }

    public function verifyDocument(PreEmploymentRequirement $requirement, User $actor): void
    {
        if ($requirement->status !== RequirementStatus::Submitted) {
            throw new DomainException(
                'Can only verify submitted documents.',
                'REQUIREMENT_NOT_SUBMITTED',
                422,
                ['current_status' => $requirement->status->value],
            );
        }

        DB::transaction(function () use ($requirement): void {
            $requirement->update([
                'status' => RequirementStatus::Verified->value,
                'verified_at' => now(),
            ]);

            $this->updateChecklistStatus($requirement->checklist);
        });
    }

    public function rejectDocument(PreEmploymentRequirement $requirement, string $remarks, User $actor): void
    {
        DB::transaction(function () use ($requirement, $remarks): void {
            $requirement->update([
                'status' => RequirementStatus::Rejected->value,
                'remarks' => $remarks,
            ]);

            $this->updateChecklistStatus($requirement->checklist);
        });
    }

    public function waiveRequirement(PreEmploymentRequirement $requirement, User $actor): void
    {
        DB::transaction(function () use ($requirement): void {
            $requirement->update([
                'status' => RequirementStatus::Waived->value,
            ]);

            $this->updateChecklistStatus($requirement->checklist);
        });
    }

    public function markComplete(PreEmploymentChecklist $checklist, User $actor): void
    {
        if (! $checklist->isComplete()) {
            throw new DomainException(
                'Not all required documents have been verified.',
                'CHECKLIST_INCOMPLETE',
                422,
                $checklist->completionProgress(),
            );
        }

        DB::transaction(function () use ($checklist, $actor): void {
            $checklist->update([
                'status' => PreEmploymentStatus::Completed->value,
                'completed_at' => now(),
                'verified_by' => $actor->id,
            ]);
        });
    }

    public function waiveChecklist(PreEmploymentChecklist $checklist, string $reason, User $actor): void
    {
        DB::transaction(function () use ($checklist, $reason, $actor): void {
            $checklist->update([
                'status' => PreEmploymentStatus::Waived->value,
                'waiver_reason' => $reason,
                'verified_by' => $actor->id,
            ]);
        });
    }

    private function updateChecklistStatus(PreEmploymentChecklist $checklist): void
    {
        $checklist->refresh();

        $hasSubmitted = $checklist->requirements()
            ->whereIn('status', [
                RequirementStatus::Submitted->value,
                RequirementStatus::Verified->value,
            ])->exists();

        if ($hasSubmitted && $checklist->status === PreEmploymentStatus::Pending) {
            $checklist->update(['status' => PreEmploymentStatus::InProgress->value]);
        }

        if ($checklist->isComplete()) {
            $checklist->update([
                'status' => PreEmploymentStatus::Completed->value,
                'completed_at' => now(),
            ]);
        }
    }
}
