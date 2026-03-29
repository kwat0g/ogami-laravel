<?php

declare(strict_types=1);

namespace App\Domains\Production\Services;

use App\Shared\Contracts\ServiceContract;

class MrpService implements ServiceContract
{
    public function summary(): array
    {
        return [
            'planned_orders' => 0,
            'material_shortages' => 0,
            'capacity_utilization' => 0,
        ];
    }
}
