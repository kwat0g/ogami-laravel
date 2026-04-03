<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Enums;

enum HiringStatus: string
{
    case Pending = 'pending';
    case PendingVpApproval = 'pending_vp_approval';
    case Hired = 'hired';
    case FailedPreEmployment = 'failed_preemployment';
    case RejectedByVp = 'rejected_by_vp';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PendingVpApproval => 'Pending VP Approval',
            self::Hired => 'Hired',
            self::FailedPreEmployment => 'Failed Pre-Employment',
            self::RejectedByVp => 'Rejected by VP',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::PendingVpApproval => 'orange',
            self::Hired => 'green-dark',
            self::FailedPreEmployment => 'red',
            self::RejectedByVp => 'red',
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
