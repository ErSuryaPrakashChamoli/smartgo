<?php

namespace App\Actions\Content;

use App\Enums\Content\RevisionState;
use App\Models\Contracts\Revisionable;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Starts a new draft of a publishable item. An item has at most one version in progress.
 */
class CreateDraft
{
    public function __construct(private RecordRevisionActivity $recordRevisionActivity) {}

    /**
     * @param  array<string, mixed>|null  $payload  the draft's content; by default the content of `$basedOn`, else the live content
     * @param  Revision|null  $basedOn  an earlier version whose content the draft copies. Its author and editors count as
     *                                  editors of the new draft, so none of them can approve their own words (OD-55).
     *
     * @throws ValidationException when the item already has a version in progress, or the payload is unsafe
     */
    public function handle(Revisionable&Model $content, User $author, ?array $payload = null, ?Revision $basedOn = null): Revision
    {
        Gate::forUser($author)->authorize('create', [Revision::class, $content]);

        $payload = $content->prepareRevisionPayload($payload ?? $basedOn?->payload ?? $content->toRevisionPayload(), forSubmission: false);
        $editorIds = $basedOn === null ? [] : array_values(array_diff(
            array_unique([$basedOn->author_id, ...($basedOn->editor_ids ?? [])]),
            [$author->getKey()],
        ));

        return DB::transaction(function () use ($content, $author, $payload, $editorIds, $basedOn): Revision {
            /** @var Revisionable&Model $content */
            $content = $content->newQuery()->lockForUpdate()->findOrFail($content->getKey());

            if ($content->revisions()->whereIn('state', RevisionState::open())->exists()) {
                throw ValidationException::withMessages(['revision' => 'This content already has a version in progress.']);
            }

            $revision = $content->revisions()->create([
                'number' => (int) $content->revisions()->max('number') + 1,
                'payload' => $payload,
                'state' => RevisionState::Draft,
                'author_id' => $author->getKey(),
                'editor_ids' => $editorIds,
            ]);

            $this->recordRevisionActivity->handle($revision, $author, 'revision_created', 'Draft started', $basedOn === null ? [] : ['based_on' => $basedOn->number]);

            return $revision;
        });
    }
}
