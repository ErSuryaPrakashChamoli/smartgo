<?php

namespace App\Actions\Content;

use App\Enums\Content\RevisionState;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Publishes every approved version whose scheduled time has come, as the person who scheduled it. The
 * rules are checked again at that moment; a version that no longer passes stays unpublished and the
 * failure is audited.
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
                    $this->makeRevisionLive->handle($revision, $scheduler, 'publish', 'revision_published', 'Scheduled content published');
                    $published++;
                } catch (AuthorizationException|ValidationException $exception) {
                    $revision->forceFill(['scheduled_for' => null, 'scheduled_by' => null])->save();

                    $this->recordRevisionActivity->handle($revision, $scheduler, 'revision_schedule_failed', 'Scheduled publication failed', [
                        'reason' => $exception->getMessage(),
                    ]);
                }
            });

        return $published;
    }
}
