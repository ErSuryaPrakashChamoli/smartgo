<?php

namespace App\Actions\Content;

use App\Enums\Content\RevisionState;
use App\Models\Contracts\Unpublishable;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Takes a live testimonial or customer story off the website at once, for example when consent is
 * withdrawn (OD-98, BR-CMS-7a). In one transaction, with the item and its versions locked in the same
 * order as publishing:
 *
 * 1. the permission and the live state are checked again on the locked rows;
 * 2. the item stops being live, so every public page drops it at once; its content is kept;
 * 3. the live version becomes "previously published", so nothing in the history is lost;
 * 4. every approved version still waiting is sent back to its authors as rejected, with its schedule
 *    cancelled, so nothing goes live again without a new approval;
 * 5. each step is audited with the actor, the reason and the version that was live.
 *
 * Putting the item back needs a new version, approved and published as usual.
 */
class UnpublishContent
{
    public const int MAX_REASON_LENGTH = 2000;

    public function __construct(private RecordRevisionActivity $recordRevisionActivity) {}

    /**
     * @throws AuthorizationException when the user may not take it down or it is not live
     * @throws ValidationException when the reason is empty or too long
     */
    public function handle(Model&Unpublishable $content, User $user, string $reason): Model&Unpublishable
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Say why it is taken down.']);
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw ValidationException::withMessages(['reason' => 'Keep the reason under 2,000 characters.']);
        }

        return DB::transaction(function () use ($content, $user, $reason): Model&Unpublishable {
            /** @var Model&Unpublishable $content */
            $content = $content->newQuery()->lockForUpdate()->findOrFail($content->getKey());
            $live = Revision::query()->lockForUpdate()->find($content->getAttribute('published_revision_id'));

            if ($live === null) {
                throw new AuthorizationException('Only live content can be taken down.');
            }

            Gate::forUser($user)->authorize('unpublish', $live->setRelation('revisionable', $content));

            $content->forceFill(['status' => 'unpublished'])->save();
            $live->forceFill(['state' => RevisionState::Superseded])->save();

            $returned = $content->revisions()->where('state', RevisionState::Approved)->lockForUpdate()->get()
                ->map(fn (Revision $revision): int => $this->returnToAuthors($revision, $content, $user, $reason));

            $this->recordRevisionActivity->handle($live, $user, 'content_unpublished', ucfirst($content->contentLabel()).' taken off the website', [
                'reason' => $reason,
                'previous_state' => 'published',
                'live_revision' => $live->number,
                'live_published_at' => $live->published_at?->toIso8601String(),
                'returned_revisions' => $returned->all(),
            ]);

            return $content;
        });
    }

    /**
     * Sends an approved version back as rejected, following the workflow's meaning of "rejected": it
     * can never be published, and its authors may revise it into a new draft.
     */
    private function returnToAuthors(Revision $revision, Model&Unpublishable $content, User $user, string $reason): int
    {
        $previous = ['previous_reviewer_id' => $revision->reviewer_id, 'was_scheduled_for' => $revision->scheduled_for?->toIso8601String(), 'was_scheduled_by' => $revision->scheduled_by];

        $revision->forceFill([
            'state' => RevisionState::Rejected,
            'reviewer_id' => $user->getKey(),
            'reviewed_at' => now(),
            'review_note' => 'Sent back automatically: the '.$content->contentLabel().' was taken off the website. Reason: '.$reason,
            'scheduled_for' => null,
            'scheduled_by' => null,
        ])->save();

        $this->recordRevisionActivity->handle($revision, $user, 'revision_returned', 'Approved version sent back when the content was taken down', [
            'reason' => $reason,
            'previous_state' => 'approved',
            ...$previous,
        ]);

        return $revision->number;
    }
}
