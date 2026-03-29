<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Enums;

enum RequisitionStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Open = 'open';
    case OnHold = 'on_hold';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Open => 'Open',
            self::OnHold => 'On Hold',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'amber',
            self::Approved => 'blue',
            self::Rejected => 'red',
            self::Open => 'green',
            self::OnHold => 'orange',
            self::Closed => 'gray-dark',
            self::Cancelled => 'red-dark',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingApproval, self::Cancelled],
            self::PendingApproval => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Open, self::Cancelled],
            self::Rejected => [self::Draft],
            self::Open => [self::OnHold, self::Closed],
            self::OnHold => [self::Open, self::Closed, self::Cancelled],
            self::Closed => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
