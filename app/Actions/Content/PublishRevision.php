<?php

namespace App\Actions\Content;

use App\Models\Revision;
use App\Models\User;

/**
 * Publishes an approved version. The approver may not publish it; the author may (BR-CMS-2a, OD-73).
 * Permission and state are checked on the locked, current row (MakeRevisionLive).
 */
class PublishRevision
{
    public function __construct(private MakeRevisionLive $makeRevisionLive) {}

    public function handle(Revision $revision, User $publisher): Revision
    {
        return $this->makeRevisionLive->handle($revision, $publisher, 'Content published');
    }
}
