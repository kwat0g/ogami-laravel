<?php

declare(strict_types=1);

namespace App\Http\Resources\HR\Recruitment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class JobOfferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'offer_number' => $this->offer_number,
            'application' => $this->whenLoaded('application', fn () => [
                'ulid' => $this->application->ulid,
                'application_number' => $this->application->application_number,
                'candidate' => $this->application->candidate ? [
                    'full_name' => $this->application->candidate->full_name,
                    'email' => $this->application->candidate->email,
                ] : null,
            ]),
            'offered_position' => $this->whenLoaded('offeredPosition', fn () => [
                'id' => $this->offeredPosition->id,
                'title' => $this->offeredPosition->title,
            ]),
            'offered_department' => $this->whenLoaded('offeredDepartment', fn () => [
                'id' => $this->offeredDepartment->id,
                'name' => $this->offeredDepartment->name,
            ]),
            'offered_salary' => $this->offered_salary,
            'employment_type' => $this->employment_type?->value,
            'employment_type_label' => $this->employment_type?->label(),
            'start_date' => (string) $this->start_date,
            'offer_letter_path' => $this->offer_letter_path,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'preparer' => $this->whenLoaded('preparer', fn () => [
                'id' => $this->preparer->id,
                'name' => $this->preparer->name,
            ]),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
