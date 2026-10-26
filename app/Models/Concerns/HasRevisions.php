<?php

namespace App\Models\Concerns;

use App\Enums\Content\RevisionState;
use App\Models\Revision;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Revision history for a publishable model (ADR-0005).
 */
trait HasRevisions
{
    /**
     * @return MorphMany<Revision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable');
    }

    /**
     * The version being worked on (draft, submitted or approved), if any. There is at most one.
     */
    public function openRevision(): ?Revision
    {
        return $this->revisions()->whereIn('state', RevisionState::open())->latest('number')->first();
    }
}
