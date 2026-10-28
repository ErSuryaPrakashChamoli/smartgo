<?php

namespace App\Enums\Crm;

/**
 * Where an enquiry stands (DATABASE_ERD §8, a subset: tours, proposals and deals come with Phases 8
 * and 9). No status reserves, books or charges anything. Lost and disqualified need a reason (BR-CRM-5).
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Lost = 'lost';
    case Disqualified = 'disqualified';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::Lost => 'Lost',
            self::Disqualified => 'Disqualified',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Contacted => 'info',
            self::Qualified => 'success',
            self::Lost, self::Disqualified => 'gray',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Lost, self::Disqualified], true);
    }

    public function needsReason(): bool
    {
        return $this->isClosed();
    }

    /**
     * The statuses an enquiry may move to from this one. A closed enquiry can only be reopened as
     * contacted; nothing returns to new.
     *
     * @return list<self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::New => [self::Contacted, self::Qualified, self::Lost, self::Disqualified],
            self::Contacted => [self::Qualified, self::Lost, self::Disqualified],
            self::Qualified => [self::Contacted, self::Lost, self::Disqualified],
            self::Lost, self::Disqualified => [self::Contacted],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
