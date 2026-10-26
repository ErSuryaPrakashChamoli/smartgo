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
    /**
     * @param  array<string, mixed>|null  $payload  the draft's content; the live content when omitted
     *
     * @throws ValidationException when the item already has a version in progress
     */
    public function handle(Revisionable&Model $content, User $author, ?array $payload = null): Revision
    {
        Gate::forUser($author)->authorize('create', [Revision::class, $content]);

        return DB::transaction(function () use ($content, $author, $payload): Revision {
            /** @var Revisionable&Model $content */
            $content = $content->newQuery()->lockForUpdate()->findOrFail($content->getKey());

            if ($content->revisions()->whereIn('state', RevisionState::open())->exists()) {
                throw ValidationException::withMessages(['revision' => 'This content already has a version in progress.']);
            }

            return $content->revisions()->create([
                'number' => (int) $content->revisions()->max('number') + 1,
                'payload' => $payload ?? $content->toRevisionPayload(),
                'state' => RevisionState::Draft,
                'author_id' => $author->getKey(),
                'editor_ids' => [],
            ]);
        });
    }
}
