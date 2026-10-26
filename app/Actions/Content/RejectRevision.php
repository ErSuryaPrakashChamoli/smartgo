<?php

namespace App\Actions\Content;

use App\Enums\Content\RevisionState;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
     * @throws ValidationException when the note is empty or too long
     */
    public function handle(Revision $revision, User $reviewer, string $note): Revision
    {
        return DB::transaction(function () use ($revision, $reviewer, $note): Revision {
            $revision = Revision::query()->lockForUpdate()->findOrFail($revision->getKey());
            Gate::forUser($reviewer)->authorize('reject', $revision);

            if (blank($note)) {
                throw ValidationException::withMessages(['review_note' => 'Say why the version is rejected.']);
            }

            if (mb_strlen($note) > 2000) {
                throw ValidationException::withMessages(['review_note' => 'Keep the note under 2,000 characters.']);
            }

            $revision->forceFill([
                'state' => RevisionState::Rejected,
                'reviewer_id' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_note' => trim($note),
            ])->save();

            $this->recordRevisionActivity->handle($revision, $reviewer, 'revision_rejected', 'Content rejected', ['note' => trim($note)]);

            return $revision;
        });
    }
}
