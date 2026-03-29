<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Enums;

enum EmploymentType: string
{
    case Regular = 'regular';
    case Contractual = 'contractual';
    case ProjectBased = 'project_based';
    case PartTime = 'part_time';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Contractual => 'Contractual',
            self::ProjectBased => 'Project-Based',
            self::PartTime => 'Part-Time',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
