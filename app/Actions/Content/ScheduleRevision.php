<?php

namespace App\Actions\Content;

use App\Models\Revision;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Schedules an approved version. Scheduling counts as publishing, so the approver may not do it, and
 * the version is published later as the person who scheduled it (BR-CMS-5, BR-CMS-2a).
 */
class ScheduleRevision
{
    public function __construct(private RecordRevisionActivity $recordRevisionActivity) {}

    /**
     * @throws ValidationException when the time is not in the future
     */
    public function handle(Revision $revision, User $publisher, CarbonInterface $publishAt): Revision
    {
        Gate::forUser($publisher)->authorize('publish', $revision);

        if (! $publishAt->isFuture()) {
            throw ValidationException::withMessages(['scheduled_for' => 'Choose a time in the future.']);
        }

        $revision->forceFill(['scheduled_for' => $publishAt, 'scheduled_by' => $publisher->getKey()])->save();

        $this->recordRevisionActivity->handle($revision, $publisher, 'revision_scheduled', 'Content scheduled for publication', [
            'scheduled_for' => $publishAt->toIso8601String(),
        ]);

        return $revision;
    }
}
