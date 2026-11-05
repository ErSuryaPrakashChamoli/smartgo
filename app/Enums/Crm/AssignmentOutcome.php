<?php

namespace App\Enums\Crm;

/**
 * What the assignment rules decided for a new enquiry (`lead_assignments.outcome`), shown to staff so they
 * can see why it has, or has not, an owner.
 */
enum AssignmentOutcome: string
{
    case Assigned = 'assigned';
    case NoRuleMatched = 'no_rule_matched';
    case NoEligibleAssignee = 'no_eligible_assignee';
    case StrategyUnavailable = 'strategy_unavailable';
    case AlreadyOwned = 'already_owned';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Assigned by a rule',
            self::NoRuleMatched => 'No rule matched',
            self::NoEligibleAssignee => 'Matched, but nobody could take it',
            self::StrategyUnavailable => 'Matched, but the rule cannot assign yet',
            self::AlreadyOwned => 'Already had an owner',
        };
    }
}
