<?php

namespace App\Actions\Content;

use App\Enums\Content\RevisionState;
use App\Models\Contracts\RequiresConsent;
use App\Models\Contracts\Unpublishable;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Takes a live testimonial or customer story off the website at once (OD-98, BR-CMS-7a), and records
 * withdrawn consent when the user says so (OD-101). In one transaction, with the item and its versions
 * locked in the same order as publishing:
 *
 * 1. the permission and the live state are checked again on the locked rows;
 * 2. the item stops being live, so every public page drops it at once; its content is kept;
 * 3. the live version becomes "previously published", so nothing in the history is lost;
 * 4. every submitted or approved version still waiting is sent back to its authors as rejected, with
 *    its schedule cancelled, so nothing goes live again without a new approval;
 * 5. if consent was withdrawn, the withdrawal is recorded on the item, and a draft in progress is sent
 *    back too, unchanged, so the consent it holds can never be submitted; otherwise the consent record
 *    and any draft are left as they are;
 * 6. each step is audited with the actor and the reason. No version's content is changed.
 *
 * Putting the item back needs a new version, approved and published as usual.
 */
class UnpublishContent
{
    public const int MAX_REASON_LENGTH = 2000;

    public function __construct(private RecordRevisionActivity $recordRevisionActivity) {}

    /**
     * @param  bool  $consentWithdrawn  the user's explicit answer to "Was consent withdrawn?"
     *
     * @throws AuthorizationException when the user may not take it down or it is not live
     * @throws ValidationException when the reason is empty or too long
     */
    public function handle(Model&Unpublishable $content, User $user, string $reason, bool $consentWithdrawn): Model&Unpublishable
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Say why it is taken down.']);
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw ValidationException::withMessages(['reason' => 'Keep the reason under 2,000 characters.']);
        }

        return DB::transaction(function () use ($content, $user, $reason, $consentWithdrawn): Model&Unpublishable {
            /** @var Model&Unpublishable $content */
            $content = $content->newQuery()->lockForUpdate()->findOrFail($content->getKey());
            $live = Revision::query()->lockForUpdate()->find($content->getAttribute('published_revision_id'));

            if ($live === null) {
                throw new AuthorizationException('Only live content can be taken down.');
            }

            Gate::forUser($user)->authorize('unpublish', $live->setRelation('revisionable', $content));

            $content->forceFill(['status' => 'unpublished'])->save();
            $live->forceFill(['state' => RevisionState::Superseded])->save();

            $withdrawsConsent = $consentWithdrawn && $content instanceof RequiresConsent;
            $returned = $content->revisions()
                ->whereIn('state', $withdrawsConsent ? RevisionState::open() : [RevisionState::Submitted, RevisionState::Approved])
                ->lockForUpdate()->get()
                ->map(fn (Revision $revision): int => $this->returnToAuthors($revision, $content, $user, $reason))
                ->values();

            if ($withdrawsConsent) {
                $this->recordWithdrawal($content, $live, $user, $reason);
            }

            $this->recordRevisionActivity->handle($live, $user, 'content_unpublished', ucfirst($content->contentLabel()).' taken off the website', [
                'reason' => $reason,
                'consent_withdrawn' => $consentWithdrawn,
                'previous_state' => 'published',
                'live_revision' => $live->number,
                'live_published_at' => $live->published_at?->toIso8601String(),
                'returned_revisions' => $returned->all(),
            ]);

            return $content;
        });
    }

    /**
     * Sends a waiting version back as rejected, following the workflow's meaning of "rejected": it can
     * never be published, its content is kept, and its authors may revise it into a new draft.
     */
    private function returnToAuthors(Revision $revision, Model&Unpublishable $content, User $user, string $reason): int
    {
        $previous = [
            'previous_state' => $revision->state->value,
            'previous_reviewer_id' => $revision->reviewer_id,
            'was_scheduled_for' => $revision->scheduled_for?->toIso8601String(),
            'was_scheduled_by' => $revision->scheduled_by,
        ];

        $revision->forceFill([
            'state' => RevisionState::Rejected,
            'reviewer_id' => $user->getKey(),
            'reviewed_at' => now(),
            'review_note' => 'Sent back automatically: the '.$content->contentLabel().' was taken off the website. Reason: '.$reason,
            'scheduled_for' => null,
            'scheduled_by' => null,
        ])->save();

        $this->recordRevisionActivity->handle($revision, $user, 'revision_returned', 'Version sent back when the content was taken down', [
            'reason' => $reason,
            ...$previous,
        ]);

        return $revision->number;
    }

    /**
     * Records the withdrawal on the item, where every version sees it.
     */
    private function recordWithdrawal(Model&RequiresConsent $content, Revision $live, User $user, string $reason): void
    {
        $content->forceFill(['consent_withdrawn_at' => now(), 'consent_withdrawn_by' => $user->getKey()])->save();

        activity('content')
            ->performedOn($content)
            ->causedBy($user)
            ->event('consent_withdrawn')
            ->withProperties([
                'content' => class_basename($content).' #'.$content->getKey(),
                'reason' => $reason,
                'withdrawn_consent_reference' => $content->getAttribute('consent_reference'),
                'live_revision' => $live->number,
            ])
            ->log('Consent withdrawn');
    }
}
