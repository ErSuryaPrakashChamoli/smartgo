<?php

namespace App\Actions\Inventory;

use App\Enums\Access\Capability;
use App\Enums\Inventory\AllocationStatus;
use App\Enums\Inventory\InventoryMode;
use App\Events\Inventory\SpaceAvailabilityChanged;
use App\Models\Deal;
use App\Models\DealSpace;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use App\Services\Inventory\SpaceAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Allocates the spaces proposed on a deal that is being won (BR-CRM-7): the only way a won deal reaches the
 * inventory, so the availability rules apply and Sales still cannot change inventory directly. WinDeal
 * calls it inside its own transaction; it adds nothing to RecordAllocation's rules and changes none of them.
 *
 * The deal is locked first, then its proposals and every proposed space in ascending id order (DATABASE_ERD
 * §5, ADR-0004), all with locking reads before any plain read: MySQL fixes a transaction's view of the data
 * at its first plain read, so a plain read before the space locks would hide an allocation committed while
 * waiting for them (the deal concurrency test caught exactly that). Only then is each proposal checked: still at the enquiry's location
 * and one the actor works on, still in the shape its space's mode allows, and free for every day of the
 * agreement (BR-AV-2 to BR-AV-4, through SpaceAvailability). One refusal refuses all, and the caller's
 * transaction rolls back every allocation already written. A proposal that already has an allocation is
 * refused, so the same deal can never be allocated twice. Each allocation is audited with the deal and
 * announced as an availability change (BR-SOT-4).
 */
class AllocateWonDeal
{
    public function __construct(
        private RecordInventoryActivity $recordInventoryActivity,
        private SpaceAvailability $spaceAvailability,
    ) {}

    /**
     * @return int the number of allocations recorded
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Deal $deal, string $agreementReference, CarbonImmutable $from, ?CarbonImmutable $until): int
    {
        return DB::transaction(function () use ($actor, $deal, $agreementReference, $from, $until): int {
            $deal = Deal::query()->lockForUpdate()->findOrFail($deal->getKey());
            $lines = DealSpace::query()->where('deal_id', $deal->getKey())->orderBy('space_id')->orderBy('id')->lockForUpdate()->get();
            $spaces = Space::query()->whereKey($lines->pluck('space_id')->unique()->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $deal->load('lead.property');
            $lines->load('seat');
            $spaces->load('property');

            if (! $deal->isOpen() || Gate::forUser($actor)->denies('update', $deal)) {
                throw new AuthorizationException('Only an open deal you may work on can have its spaces allocated.');
            }

            if ($lines->contains(fn (DealSpace $line): bool => $line->space_allocation_id !== null)) {
                throw ValidationException::withMessages(['agreement_reference' => 'This deal\'s spaces are already allocated.']);
            }

            $today = BusinessDay::today();

            foreach ($lines as $line) {
                $space = $spaces->get($line->space_id)
                    ?? throw ValidationException::withMessages(['agreement_reference' => 'A proposed space no longer exists. Withdraw it from the deal first.']);
                $this->checkProposal($actor, $deal, $space, $line);

                if (($refusal = $this->spaceAvailability->refusalForAllocation($space, $line->seat, $this->seatsTaken($space, $line), $from, $until)) !== null) {
                    throw ValidationException::withMessages(['agreement_reference' => $space->displayName().': '.$refusal]);
                }

                $allocation = (new SpaceAllocation)->forceFill([
                    'space_id' => $space->getKey(),
                    'space_seat_id' => $line->space_seat_id,
                    'agreement_reference' => $agreementReference,
                    'seats_allocated' => $this->seatsTaken($space, $line),
                    'starts_on' => $from,
                    'ends_on' => $until,
                    'status' => $from->greaterThan($today) ? AllocationStatus::Reserved : AllocationStatus::Active,
                    'source' => 'manual',
                    'created_by' => $actor->getKey(),
                ]);
                $allocation->save();
                $line->forceFill(['space_allocation_id' => $allocation->getKey()])->save();

                $this->recordInventoryActivity->handle($allocation, $actor, 'allocation_added', 'Allocation recorded', 'Deal '.$deal->getKey().' won (enquiry '.$deal->lead->reference.')', [
                    'property' => $space->property->code,
                    'space' => $space->code,
                    'deal_id' => $deal->getKey(),
                    'attributes' => [...$allocation->only(['agreement_reference', 'space_seat_id', 'seats_allocated']), 'starts_on' => $from->toDateString(), 'ends_on' => $until?->toDateString()],
                ]);
            }

            $spaces->each(fn (Space $space) => SpaceAvailabilityChanged::dispatch($space, 'allocation'));

            return $lines->count();
        });
    }

    /**
     * Whether the proposal may still become an allocation: the actor works on its location, it is still at
     * the enquiry's location, and it still has the shape the space's mode allows.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private function checkProposal(User $actor, Deal $deal, Space $space, DealSpace $line): void
    {
        if (! $actor->hasCapabilityForProperty(Capability::ManageLeads, $space->property)) {
            throw new AuthorizationException('You do not work on SmartGro '.$space->property->name.', so you cannot allocate '.$space->displayName().'.');
        }

        if ($deal->lead->property_id !== null && $space->property_id !== $deal->lead->property_id) {
            throw ValidationException::withMessages(['agreement_reference' => $space->displayName().' is not at the enquiry\'s location. Withdraw it from the deal first.']);
        }

        $fits = match ($space->inventory_mode) {
            InventoryMode::Exclusive, InventoryMode::Pooled => $line->space_seat_id === null,
            InventoryMode::PerSeat => $line->space_seat_id !== null,
            InventoryMode::Hybrid => true,
        };

        if (! $fits) {
            throw ValidationException::withMessages(['agreement_reference' => $space->displayName().' is now '.strtolower($space->inventory_mode->label()).', so this proposal no longer fits it. Withdraw it and propose it again.']);
        }
    }

    /**
     * The seats the allocation takes: the whole space's current capacity, one seat, or the number proposed.
     */
    private function seatsTaken(Space $space, DealSpace $line): int
    {
        return match (true) {
            $space->inventory_mode === InventoryMode::Exclusive => $space->capacity_seats,
            $line->space_seat_id !== null => 1,
            default => $line->seats,
        };
    }
}
