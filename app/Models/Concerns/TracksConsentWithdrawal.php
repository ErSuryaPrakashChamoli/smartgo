<?php

namespace App\Models\Concerns;

use App\Enums\Content\RevisionState;
use App\Models\Revision;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Withdrawn consent for testimonials and customer stories (OD-101). The withdrawal is recorded on the
 * item (`consent_withdrawn_at`, `consent_withdrawn_by`), never on a version, so it holds across every
 * version. While it is set:
 *
 * - a new draft starts with its consent cleared (CreateDraft);
 * - submitting and publishing need consent on file, a consent reference that was not part of the
 *   withdrawn consent (see freshConsentReferenceRule()), and the explicit confirmation
 *   `consent_reconfirmed`;
 * - publishing such a version re-establishes consent, which clears the record and is audited.
 *
 * A reference is only a pointer to evidence kept elsewhere, not proof that consent is valid. Which
 * evidence counts is the owner's and counsel's decision; the application only requires a new
 * reference and the confirmation.
 *
 * Concurrency: the withdrawn references are fixed when consent is withdrawn, because the take-down
 * locks the item and closes every version that exists at that moment; later versions can only be
 * created under the same lock after it. An item has at most one open version, so two versions of one
 * item are never checked against each other at the same time; submission locks the version, publication
 * locks the item, and the take-down locks both, so a withdrawal cannot slip between a check and the
 * change it guards. References are compared within one item only.
 */
trait TracksConsentWithdrawal
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function consentWithdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consent_withdrawn_by');
    }

    public function consentWithdrawn(): bool
    {
        return $this->consent_withdrawn_at !== null;
    }

    public function withoutConsent(array $payload): array
    {
        return [...$payload, 'consent_on_file' => false, 'consent_reference' => null, 'consent_reconfirmed' => false];
    }

    /**
     * Extra rules while consent is withdrawn; none otherwise, so existing content behaves as before.
     *
     * @return array<string, list<mixed>>
     */
    protected function withdrawnConsentRules(bool $forSubmission): array
    {
        if (! $forSubmission || ! $this->consentWithdrawn()) {
            return ['consent_reconfirmed' => ['nullable', 'boolean']];
        }

        return [
            'consent_reference' => ['required', $this->freshConsentReferenceRule()],
            'consent_reconfirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function withdrawnConsentMessages(): array
    {
        return [
            'consent_reconfirmed.accepted' => 'Consent was withdrawn. Confirm that it was given again after the withdrawal.',
        ];
    }

    /**
     * Re-establishes consent when a version with new consent goes live, inside the publishing
     * transaction. The version's author confirmed it, someone else approved it and a third person or
     * the author published it, so the step is explicit and audited.
     */
    protected function reestablishConsentOnPublication(Revision $revision): void
    {
        if (! $this->consentWithdrawn()) {
            return;
        }

        $withdrawnAt = $this->consent_withdrawn_at;
        $withdrawnBy = $this->consent_withdrawn_by;

        $this->forceFill(['consent_withdrawn_at' => null, 'consent_withdrawn_by' => null])->save();

        activity('content')
            ->performedOn($this)
            ->causedBy($revision->publisher)
            ->event('consent_reestablished')
            ->withProperties([
                'content' => class_basename($this).' #'.$this->getKey(),
                'revision' => $revision->number,
                'consent_reference' => $revision->payload['consent_reference'] ?? null,
                'confirmed_by_author_id' => $revision->author_id,
                'reviewer_id' => $revision->reviewer_id,
                'published_by' => $revision->published_by,
                'withdrawn_at' => $withdrawnAt?->toIso8601String(),
                'withdrawn_by' => $withdrawnBy,
            ])
            ->log('Consent re-established with a new version');
    }

    /**
     * The withdrawn consent is the live record's reference and the references of every version that
     * existed when consent was withdrawn; the take-down closed all of them. References are compared
     * ignoring letter case and surrounding spaces. A version created after the withdrawal holds only new
     * evidence, so its reference stays usable even if that version is rejected. A version created in
     * the same second as the withdrawal is treated as earlier, which can only refuse a reference, never
     * let an old one through. The version being checked is the item's only open one, so it is left out.
     */
    private function freshConsentReferenceRule(): Closure
    {
        $normalise = fn (?string $reference): string => mb_strtolower(trim((string) $reference));

        return function (string $attribute, mixed $value, Closure $fail) use ($normalise): void {
            $used = $this->revisions()
                ->whereNotIn('state', RevisionState::open())
                ->where('created_at', '<=', $this->consent_withdrawn_at)
                ->get(['payload'])
                ->map(fn (Revision $revision): string => $normalise($revision->payload['consent_reference'] ?? null))
                ->push($normalise($this->consent_reference))
                ->filter()
                ->all();

            if (in_array($normalise(is_string($value) ? $value : null), $used, true)) {
                $fail('Consent was withdrawn. Enter the reference of the new consent; this one was used before.');
            }
        };
    }
}
