<?php

namespace App\Actions\Content;

use App\Enums\Content\RevisionState;
use App\Models\Contracts\Revisionable;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Sends a complete draft for approval by someone who did not work on it (BR-CMS-1, BR-CMS-2). From
 * now on the version cannot change.
 */
class SubmitRevision
{
    public function __construct(private RecordRevisionActivity $recordRevisionActivity) {}

    /**
     * @throws ValidationException when the draft is incomplete or invalid
     */
    public function handle(Revision $revision, User $submitter): Revision
    {
        return DB::transaction(function () use ($revision, $submitter): Revision {
            $revision = Revision::query()->lockForUpdate()->findOrFail($revision->getKey());
            Gate::forUser($submitter)->authorize('submit', $revision);

            /** @var Revisionable $content */
            $content = $revision->revisionable;
            $content->prepareRevisionPayload($revision->payload, forSubmission: true);

            $revision->forceFill(['state' => RevisionState::Submitted, 'submitted_at' => now()])->save();

            $this->recordRevisionActivity->handle($revision, $submitter, 'revision_submitted', 'Content submitted for approval');

            return $revision;
        });
    }
}
