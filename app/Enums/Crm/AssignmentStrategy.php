<?php

namespace App\Enums\Crm;

/**
 * How an assignment rule picks the owner of a new enquiry among its people (FR-CRM-2, DATABASE_ERD §6).
 */
enum AssignmentStrategy: string
{
    case RoundRobin = 'round_robin';
    case FixedUser = 'fixed_user';
    case LeastLoaded = 'least_loaded';

    public function label(): string
    {
        return match ($this) {
            self::RoundRobin => 'Round-robin',
            self::FixedUser => 'Fixed person',
            self::LeastLoaded => 'Least loaded',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RoundRobin => 'Each new enquiry goes to the next person in the list, in turn.',
            self::FixedUser => 'Every matching enquiry goes to one person.',
            self::LeastLoaded => 'Each new enquiry goes to the person with the fewest enquiries counted as workload; a tie goes to whoever is higher in the list.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $strategy): array => [$strategy->value => $strategy->label()])->all();
    }
}
