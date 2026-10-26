<?php

namespace App\Actions\Content;

use App\Enums\Content\RevisionState;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Publishes every approved version whose scheduled time has come, as the person who scheduled it. The
 * rules are checked again at that moment. A version that no longer passes stays approved and
 * unpublished, its schedule is cleared, and the failure is audited with the reason, so staff can see
 * it and publish or schedule again. A version that someone published or unscheduled in the meantime
 * is skipped.
 */
class PublishScheduledRevisions
{
    public function __construct(
        private MakeRevisionLive $makeRevisionLive,
        private RecordRevisionActivity $recordRevisionActivity,
    ) {}

    /**
     * @return int the number of versions published
     */
    public function handle(): int
    {
        $published = 0;

        Revision::query()
            ->where('state', RevisionState::Approved)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->lazyById()
            ->each(function (Revision $revision) use (&$published): void {
                /** @var User $scheduler */
                $scheduler = $revision->scheduler;

                try {
                    $this->makeRevisionLive->handle($revision, $scheduler, 'Scheduled content published');
                    $published++;
                } catch (AuthorizationException|ValidationException $exception) {
                    $current = $revision->fresh();

                    if ($current === null || $current->state !== RevisionState::Approved || $current->scheduled_by !== $scheduler->getKey()) {
                        return;
                    }

                    $current->forceFill(['scheduled_for' => null, 'scheduled_by' => null])->save();

                    $this->recordRevisionActivity->handle($current, $scheduler, 'revision_schedule_failed', 'Scheduled publication failed', [
                        'reason' => $exception->getMessage(),
                        'was_scheduled_for' => $revision->scheduled_for?->toIso8601String(),
                    ]);
                }
            });

        return $published;
    }
}
