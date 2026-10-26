<?php

namespace App\Actions\Content;

use App\Enums\Content\RevisionState;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Rejects a submitted version with a note to its authors (BR-CMS-1). A rejected version can never be
 * published; the authors start a new draft.
 */
class RejectRevision
{
    public function __construct(private RecordRevisionActivity $recordRevisionActivity) {}

    /**
     * @throws ValidationException when the note is empty
     */
    public function handle(Revision $revision, User $reviewer, string $note): Revision
    {
        Gate::forUser($reviewer)->authorize('reject', $revision);

        if (blank($note)) {
            throw ValidationException::withMessages(['review_note' => 'Say why the version is rejected.']);
        }

        $revision->forceFill([
            'state' => RevisionState::Rejected,
            'reviewer_id' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        $this->recordRevisionActivity->handle($revision, $reviewer, 'revision_rejected', 'Content rejected', ['note' => $note]);

        return $revision;
    }
}
