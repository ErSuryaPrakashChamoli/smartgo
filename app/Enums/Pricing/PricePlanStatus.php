<?php

namespace App\Enums\Pricing;

/**
 * A price plan's state (DATABASE_ERD §4): Finance drafts and submits it; another person who may approve
 * prices activates it (BR-PR-2); it is retired, never deleted, when it stops applying early. An active
 * plan is shown as scheduled, current or ended by its dates.
 */
enum PricePlanStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Awaiting activation',
            self::Active => 'Active',
            self::Retired => 'Retired',
        };
    }
}
