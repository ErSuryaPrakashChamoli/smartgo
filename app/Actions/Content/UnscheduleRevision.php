<?php

namespace App\Actions\Content;

use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Cancels a scheduled publication. The version stays approved and can be published or scheduled again.
 */
class UnscheduleRevision
{
    public function __construct(private RecordRevisionActivity $recordRevisionActivity) {}

    public function handle(Revision $revision, User $publisher): Revision
    {
        return DB::transaction(function () use ($revision, $publisher): Revision {
            $revision = Revision::query()->lockForUpdate()->findOrFail($revision->getKey());
            Gate::forUser($publisher)->authorize('unschedule', $revision);

            $scheduledFor = $revision->scheduled_for;
            $revision->forceFill(['scheduled_for' => null, 'scheduled_by' => null])->save();

            $this->recordRevisionActivity->handle($revision, $publisher, 'revision_unscheduled', 'Scheduled publication cancelled', [
                'was_scheduled_for' => $scheduledFor?->toIso8601String(),
            ]);

            return $revision;
        });
    }
}
