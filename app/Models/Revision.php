<?php

namespace App\Models;

use App\Enums\Content\RevisionState;
use Database\Factories\RevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One version of a publishable item (ADR-0005). The author, everyone else who edited it, the approver
 * (`reviewer_id`) and the publisher (`published_by`) are recorded separately (OD-65, OD-73, OD-85).
 */
#[Fillable(['number', 'payload', 'state', 'author_id', 'editor_ids'])]
class Revision extends Model
{
    /** @use HasFactory<RevisionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'author_id' => 'integer',
            'editor_ids' => 'array',
            'reviewer_id' => 'integer',
            'published_by' => 'integer',
            'scheduled_by' => 'integer',
            'state' => RevisionState::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'scheduled_for' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    /**
     * Whether the user wrote or changed this version; such a person may never approve it (SECURITY_MODEL §11.6).
     */
    public function wasEditedBy(User $user): bool
    {
        return $this->author_id === $user->getKey() || in_array($user->getKey(), $this->editor_ids ?? [], true);
    }
}
