<?php

namespace App\Actions\Content;

use App\Models\Contracts\Revisionable;
use App\Models\Revision;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Schedules an approved version. Scheduling counts as publishing, so the approver may not do it, and
 * the version is published later as the person who scheduled it, after the rules are checked again
 * (BR-CMS-5, BR-CMS-2a). Until then it stays approved and unpublished.
 */
class ScheduleRevision
{
    public function __construct(private RecordRevisionActivity $recordRevisionActivity) {}

    /**
     * @throws ValidationException when the time is not in the future or the version could not be published
     */
    public function handle(Revision $revision, User $publisher, CarbonInterface $publishAt): Revision
    {
        return DB::transaction(function () use ($revision, $publisher, $publishAt): Revision {
            $revision = Revision::query()->lockForUpdate()->findOrFail($revision->getKey());
            Gate::forUser($publisher)->authorize('publish', $revision);

            if (! $publishAt->isFuture()) {
                throw ValidationException::withMessages(['scheduled_for' => 'Choose a time in the future.']);
            }

            if ($publishAt->isAfter(now()->addYear())) {
                throw ValidationException::withMessages(['scheduled_for' => 'Choose a time within the next year.']);
            }

            /** @var Revisionable $content */
            $content = $revision->revisionable;
            $content->prepareRevisionPayload($revision->payload, forSubmission: true);

            $revision->forceFill(['scheduled_for' => $publishAt, 'scheduled_by' => $publisher->getKey()])->save();

            $this->recordRevisionActivity->handle($revision, $publisher, 'revision_scheduled', 'Content scheduled for publication', [
                'scheduled_for' => $publishAt->toIso8601String(),
            ]);

            return $revision;
        });
    }
}
