<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounting;

use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JournalEntry */
final class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'je_number' => $this->je_number,
            'date' => $this->date->toDateString(),
            'description' => $this->description,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'status' => $this->status,
            'fiscal_period_id' => $this->fiscal_period_id,
            'reversal_of' => $this->reversal_of,
            'reversal_of_ulid' => $this->reversalOf?->ulid,
            'created_by' => $this->created_by,
            'submitted_by' => $this->submitted_by,
            'posted_by' => $this->posted_by,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'is_auto_posted' => $this->isAutoPosted(),
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
            'fiscal_period' => new FiscalPeriodResource($this->whenLoaded('fiscalPeriod')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
