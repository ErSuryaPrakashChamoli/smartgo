<?php

namespace App\Models;

use App\Enums\Crm\LeadActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internal note or follow-up on an enquiry. Staff-only, never edited or deleted.
 */
class LeadActivity extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => LeadActivityType::class, 'due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isOpenFollowUp(): bool
    {
        return $this->type === LeadActivityType::FollowUp && $this->completed_at === null;
    }
}
