<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Enums;

enum OfferStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'purple',
            self::Accepted => 'green',
            self::Rejected => 'red',
            self::Expired => 'gray-dark',
            self::Withdrawn => 'gray',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Withdrawn],
            self::Sent => [self::Accepted, self::Rejected, self::Expired, self::Withdrawn],
            self::Accepted => [],
            self::Rejected => [],
            self::Expired => [],
            self::Withdrawn => [],
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
