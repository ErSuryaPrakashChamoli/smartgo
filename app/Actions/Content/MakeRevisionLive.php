<?php

namespace App\Actions\Content;

use App\Enums\Content\RevisionState;
use App\Events\Content\ContentPublished;
use App\Models\Contracts\Revisionable;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Puts a version live in one transaction (ADR-0005): the item and the version are locked first, the
 * caller's permission is checked again under the lock, the version previously live is superseded, and
 * the payload is copied into the live record. Used by publishing, scheduled publishing and rollback.
 */
class MakeRevisionLive
{
    public function __construct(private RecordRevisionActivity $recordRevisionActivity) {}

    /**
     * @param  'publish'|'rollBack'  $ability
     */
    public function handle(Revision $revision, User $publisher, string $ability, string $event, string $description): Revision
    {
        return DB::transaction(function () use ($revision, $publisher, $ability, $event, $description): Revision {
            /** @var Revisionable&Model $content */
            $content = $revision->revisionable()->lockForUpdate()->firstOrFail();

            $revision = Revision::query()->lockForUpdate()->findOrFail($revision->getKey());
            Gate::forUser($publisher)->authorize($ability, $revision);

            $content->revisions()->whereKeyNot($revision->getKey())->where('state', RevisionState::Published)
                ->update(['state' => RevisionState::Superseded]);

            $revision->forceFill([
                'state' => RevisionState::Published,
                'published_by' => $publisher->getKey(),
                'published_at' => now(),
                'scheduled_for' => null,
                'scheduled_by' => null,
            ])->save();

            $content->applyPublishedRevision($revision);

            $this->recordRevisionActivity->handle($revision, $publisher, $event, $description);

            ContentPublished::dispatch($revision);

            return $revision;
        });
    }
}
