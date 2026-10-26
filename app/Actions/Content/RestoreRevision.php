<?php

namespace App\Actions\Content;

use App\Models\Contracts\Revisionable;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Restores an earlier published version by copying it into a new draft (OD-88, BR-CMS-3). The
 * historical version is never changed, and the new draft needs approval and publication like any
 * other. The approver of the restored version may not restore it (BR-CMS-2a).
 */
class RestoreRevision
{
    public function __construct(
        private CreateDraft $createDraft,
        private RecordRevisionActivity $recordRevisionActivity,
    ) {}

    /**
     * @throws ValidationException when the item already has a version in progress
     */
    public function handle(Revision $revision, User $restorer): Revision
    {
        $revision = $revision->fresh() ?? $revision;
        Gate::forUser($restorer)->authorize('restore', $revision);

        /** @var Revisionable&Model $content */
        $content = $revision->revisionable;
        $draft = $this->createDraft->handle($content, $restorer, basedOn: $revision);

        $this->recordRevisionActivity->handle($draft, $restorer, 'revision_restored', 'Earlier version restored as a new draft', [
            'restored_from' => $revision->number,
        ]);

        return $draft;
    }
}
