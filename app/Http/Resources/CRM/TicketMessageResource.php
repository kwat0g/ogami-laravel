<?php

declare(strict_types=1);

namespace App\Http\Resources\CRM;

use App\Domains\CRM\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketMessage */
final class TicketMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TicketMessage $message */
        $message = $this->resource;

        return [
            'id' => $message->id,
            'body' => $message->body,
            'is_internal' => $message->is_internal ?? false,
            'sender_type' => $message->sender_type ?? null,
            'sender_id' => $message->sender_id ?? null,
            'sender' => $this->whenLoaded('sender', fn () => $message->sender ? [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
            ] : null),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
