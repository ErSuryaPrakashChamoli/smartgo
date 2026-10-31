<?php

namespace App\Enums\Pricing;

/**
 * An offer's state (DATABASE_ERD §4). "Expired" is never written: an active offer is expired once its
 * end has passed, so it stops applying without any job (FR-INV-5).
 */
enum OfferStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Awaiting approval',
            self::Active => 'Active',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'warning',
            self::Active => 'success',
            self::Expired => 'gray',
        };
    }
}
