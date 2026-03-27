<?php

declare(strict_types=1);

namespace App\Http\Resources\CRM;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class TicketCollection extends ResourceCollection
{
    public $collects = TicketResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
