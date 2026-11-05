<?php

namespace App\Models;

use App\Enums\Crm\AssignmentOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the assignment rules decided for one new enquiry, recorded once (AssignNewLead): the rule that
 * matched, if any, the person given the enquiry, or why it stayed unassigned.
 */
class LeadAssignment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => AssignmentOutcome::class,
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<AssignmentRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AssignmentRule::class, 'assignment_rule_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
