<?php

namespace App\Actions\Content;

use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Publishes an approved version. The approver may not publish it; the author may (BR-CMS-2a, OD-73).
 */
class PublishRevision
{
    public function __construct(private MakeRevisionLive $makeRevisionLive) {}

    public function handle(Revision $revision, User $publisher): Revision
    {
        Gate::forUser($publisher)->authorize('publish', $revision);

        return $this->makeRevisionLive->handle($revision, $publisher, 'publish', 'revision_published', 'Content published');
    }
}
