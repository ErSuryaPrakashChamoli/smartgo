<?php

namespace App\Actions\Content;

use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Republishes an earlier published version (BR-CMS-3). Rolling back is publishing, so the person who
 * approved that version may not do it (BR-CMS-2a).
 */
class RollBackRevision
{
    public function __construct(private MakeRevisionLive $makeRevisionLive) {}

    public function handle(Revision $revision, User $publisher): Revision
    {
        Gate::forUser($publisher)->authorize('rollBack', $revision);

        return $this->makeRevisionLive->handle($revision, $publisher, 'rollBack', 'revision_rolled_back', 'Content rolled back to an earlier version');
    }
}
