<?php

namespace App\Actions\Pricing;

use App\Enums\Access\Capability;
use App\Enums\Pricing\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves an offer through its states (FR-INV-5, BR-PR-5), each audited, under a lock on the offer:
 *
 * - submit: a drafter sends it for approval;
 * - approve: a person granted `pricing.approve` who did not draft, change or submit it activates it
 *   (SECURITY_MODEL §3.1 "Price plan / offer activation", not own); it must still have time to run;
 * - return: the approver sends it back with a reason, or a drafter withdraws it;
 * - end: stops an active offer now, with a reason. Otherwise it expires on its own at its end.
 */
class ChangeOfferState
{
    public function __construct(private RecordPricingActivity $recordPricingActivity) {}

    /**
     * @param  'submit'|'approve'|'return'|'end'  $change
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Offer $offer, string $change, ?string $reason = null): Offer
    {
        $reason = trim((string) $reason);

        if (in_array($change, ['return', 'end'], true) && ($reason === '' || mb_strlen($reason) > 500 || preg_match('/[<>]/', $reason) === 1)) {
            throw ValidationException::withMessages(['reason' => 'Give the reason in plain text, up to 500 characters.']);
        }

        return DB::transaction(function () use ($user, $offer, $change, $reason): Offer {
            $offer = Offer::query()->lockForUpdate()->findOrFail($offer->getKey());
            $isAuthor = in_array($user->getKey(), $offer->authorIds(), true);

            [$event, $description] = match ($change) {
                'submit' => $this->step($user->can(Capability::ManageOffers), $offer, OfferStatus::Draft, ['status' => OfferStatus::PendingApproval, 'submitted_by' => $user->getKey(), 'submitted_at' => now()], ['offer_submitted', 'Offer submitted for approval']),
                'approve' => $this->approve($user, $offer, $isAuthor),
                'return' => $this->step($isAuthor ? $user->can(Capability::ManageOffers) : $user->can(Capability::ApprovePrices), $offer, OfferStatus::PendingApproval, ['status' => OfferStatus::Draft, 'submitted_by' => null, 'submitted_at' => null], $isAuthor ? ['offer_withdrawn', 'Offer withdrawn'] : ['offer_returned', 'Offer returned to its author']),
                'end' => $this->end($user, $offer),
            };

            $offer->save();
            $this->recordPricingActivity->handle($offer, $user, $event, $description, $reason !== '' ? ['reason' => $reason] : []);

            return $offer;
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array{0: string, 1: string}  $entry
     * @return array{0: string, 1: string}
     */
    private function step(bool $allowed, Offer $offer, OfferStatus $expected, array $changes, array $entry): array
    {
        if (! $allowed) {
            throw new AuthorizationException('You cannot do this to an offer.');
        }

        if ($offer->status !== $expected) {
            throw ValidationException::withMessages(['offer' => 'This offer is '.strtolower($offer->status->label()).' now; reload it.']);
        }

        $offer->forceFill($changes);

        return $entry;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function approve(User $user, Offer $offer, bool $isAuthor): array
    {
        if (! $user->can(Capability::ApprovePrices) || $isAuthor) {
            throw new AuthorizationException('An offer is approved by Finance or the Super Admin, never by someone who drafted, changed or submitted it.');
        }

        if ($offer->ends_at->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['ends_at' => 'This offer has already ended. Return it for new dates.']);
        }

        if (! $offer->offerings()->where('offerings.is_active', true)->exists()) {
            throw ValidationException::withMessages(['offer' => 'None of the categories this offer applies to is offered any more. Return it to its author.']);
        }

        return $this->step(true, $offer, OfferStatus::PendingApproval, ['status' => OfferStatus::Active, 'approved_by' => $user->getKey(), 'approved_at' => now()], ['offer_approved', 'Offer approved']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function end(User $user, Offer $offer): array
    {
        if (! $user->can(Capability::ManageOffers) && ! $user->can(Capability::ApprovePrices)) {
            throw new AuthorizationException('You cannot end offers.');
        }

        if ($offer->stateAt(now()) !== OfferStatus::Active) {
            throw ValidationException::withMessages(['offer' => 'Only an offer that applies now or later can be ended.']);
        }

        return $this->step(true, $offer, OfferStatus::Active, ['ends_at' => now()->max($offer->starts_at)], ['offer_ended', 'Offer ended early']);
    }
}
