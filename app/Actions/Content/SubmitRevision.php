<?php

namespace App\Actions\Content;

use App\Enums\Content\RevisionState;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Sends a draft for approval by someone who did not work on it (BR-CMS-1, BR-CMS-2).
 */
class SubmitRevision
{
    public function __construct(private RecordRevisionActivity $recordRevisionActivity) {}

    public function handle(Revision $revision, User $submitter): Revision
    {
        Gate::forUser($submitter)->authorize('submit', $revision);

        $revision->forceFill(['state' => RevisionState::Submitted, 'submitted_at' => now()])->save();

        $this->recordRevisionActivity->handle($revision, $submitter, 'revision_submitted', 'Content submitted for approval');

        return $revision;
    }
}
