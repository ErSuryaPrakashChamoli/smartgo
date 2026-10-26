<?php

namespace App\Actions\Content;

use App\Models\Contracts\Revisionable;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Changes a draft. Anyone who changes it counts as one of its authors and may not approve it
 * (SECURITY_MODEL §11.6). The draft is re-read under a lock, so a version submitted in the meantime
 * is never changed.
 */
class UpdateDraft
{
    public function __construct(private RecordRevisionActivity $recordRevisionActivity) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException when the payload is unsafe
     */
    public function handle(Revision $revision, User $editor, array $payload): Revision
    {
        return DB::transaction(function () use ($revision, $editor, $payload): Revision {
            $revision = Revision::query()->lockForUpdate()->findOrFail($revision->getKey());
            Gate::forUser($editor)->authorize('update', $revision);

            /** @var Revisionable $content */
            $content = $revision->revisionable;
            $payload = $content->prepareRevisionPayload($payload, forSubmission: false);

            $editorIds = $revision->editor_ids ?? [];

            if (! $revision->wasEditedBy($editor)) {
                $editorIds[] = $editor->getKey();
            }

            $revision->forceFill(['payload' => $payload, 'editor_ids' => $editorIds])->save();

            $this->recordRevisionActivity->handle($revision, $editor, 'revision_edited', 'Draft changed');

            return $revision;
        });
    }
}
