<?php

namespace App\Actions\Content;

use App\Enums\Content\RevisionState;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Approves a submitted version. One approval by someone who did not work on it is enough (OD-73).
 */
class ApproveRevision
{
    public function __construct(private RecordRevisionActivity $recordRevisionActivity) {}

    public function handle(Revision $revision, User $approver): Revision
    {
        return DB::transaction(function () use ($revision, $approver): Revision {
            $revision = Revision::query()->lockForUpdate()->findOrFail($revision->getKey());
            Gate::forUser($approver)->authorize('approve', $revision);

            $revision->forceFill([
                'state' => RevisionState::Approved,
                'reviewer_id' => $approver->getKey(),
                'reviewed_at' => now(),
                'review_note' => null,
            ])->save();

            $this->recordRevisionActivity->handle($revision, $approver, 'revision_approved', 'Content approved');

            return $revision;
        });
    }
}
