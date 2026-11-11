<?php

namespace App\Actions\Crm;

use App\Enums\Access\Capability;
use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Models\Deal;
use App\Models\DealSpace;
use App\Models\Space;
use App\Models\SpaceSeat;
use App\Models\User;
use App\Services\Inventory\SpaceAvailability;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Proposes a space from the canonical inventory on an open deal (FR-CRM-4), in the shape an allocation
 * would take it: a whole space, one seat of a per-seat zone (or of a hybrid zone), or a number of seats
 * of a pool or hybrid zone, never more than it offers. The space must be in use, at the enquiry's location
 * when it names one, and at a location the actor works on; the seat must be one of the space's active
 * seats. A proposal reserves nothing: availability is shown live and checked again, under the space's
 * lock, only when the deal is won (BR-CRM-7). The same space or seat is proposed once per deal. Audited.
 */
class ProposeDealSpace
{
    public function __construct(private SpaceAvailability $spaceAvailability) {}

    /**
     * @param  array<string, mixed>  $data  space_id, space_seat_id, seats
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Deal $deal, array $data): DealSpace
    {
        $validated = Validator::make($data, [
            'space_id' => ['required', 'integer'],
            'space_seat_id' => ['nullable', 'integer'],
            'seats' => ['nullable', 'integer', 'between:1,2000'],
        ], [], ['space_id' => 'space', 'space_seat_id' => 'seat'])->validate();

        return DB::transaction(function () use ($actor, $deal, $validated): DealSpace {
            $deal = Deal::query()->with('lead.property')->lockForUpdate()->findOrFail($deal->getKey());
            Gate::forUser($actor)->authorize('update', $deal);

            if (! $deal->isOpen()) {
                throw ValidationException::withMessages(['space_id' => 'This deal is '.strtolower($deal->outcome()->label()).', so its proposed spaces stay as they were.']);
            }

            $space = Space::query()->with('property')->find((int) $validated['space_id'])
                ?? throw ValidationException::withMessages(['space_id' => 'Choose a space from the list.']);

            if ($deal->lead->property_id !== null && $space->property_id !== $deal->lead->property_id) {
                throw ValidationException::withMessages(['space_id' => 'The enquiry is about SmartGro '.$deal->lead->property->name.', so propose one of its spaces.']);
            }

            if (! $actor->hasCapabilityForProperty(Capability::ManageLeads, $space->property)) {
                throw new AuthorizationException('You do not work on SmartGro '.$space->property->name.'.');
            }

            if ($space->operational_status !== SpaceStatus::Active) {
                throw ValidationException::withMessages(['space_id' => 'This space is '.strtolower($space->operational_status->label()).', so it cannot be proposed.']);
            }

            $seat = filled($validated['space_seat_id'] ?? null)
                ? (SpaceSeat::query()->where('space_id', $space->getKey())->where('is_active', true)->find((int) $validated['space_seat_id'])
                    ?? throw ValidationException::withMessages(['space_seat_id' => 'Choose one of this zone\'s seats in use.']))
                : null;
            $seats = $this->seatsProposed($space, $seat, filled($validated['seats'] ?? null) ? (int) $validated['seats'] : null);

            $alreadyProposed = DealSpace::query()->where('deal_id', $deal->getKey())->where('space_id', $space->getKey())
                ->when($seat === null, fn ($query) => $query->whereNull('space_seat_id'), fn ($query) => $query->where('space_seat_id', $seat->getKey()))
                ->exists();

            if ($alreadyProposed) {
                throw ValidationException::withMessages(['space_id' => ($seat === null ? 'This space' : 'This seat').' is already proposed on the deal.']);
            }

            $line = (new DealSpace)->forceFill([
                'deal_id' => $deal->getKey(),
                'space_id' => $space->getKey(),
                'space_seat_id' => $seat?->getKey(),
                'seats' => $seats,
                'created_by' => $actor->getKey(),
            ]);
            $line->save();

            activity('leads')
                ->performedOn($deal)
                ->causedBy($actor)
                ->event('deal_space_proposed')
                ->withProperties(['deal_id' => $deal->getKey(), 'space' => $space->code, 'seat' => $seat?->label, 'seats' => $seats])
                ->log('Space proposed: '.$space->displayName().', '.lcfirst($line->portion()));

            return $line;
        });
    }

    /**
     * How many seats the proposal takes, by the space's inventory mode, as an allocation would (FR-INV-3).
     *
     * @throws ValidationException
     */
    private function seatsProposed(Space $space, ?SpaceSeat $seat, ?int $seats): int
    {
        $capacity = $this->spaceAvailability->capacityOf($space);

        return match (true) {
            $space->inventory_mode === InventoryMode::Exclusive => $seat === null ? $space->capacity_seats : throw ValidationException::withMessages(['space_seat_id' => 'A whole space is proposed without choosing a seat.']),
            $seat !== null && ! $space->inventory_mode->hasSeats() => throw ValidationException::withMessages(['space_seat_id' => 'This zone\'s seats are not assigned individually: give a number of seats instead.']),
            $seat !== null => 1,
            $space->inventory_mode === InventoryMode::PerSeat => throw ValidationException::withMessages(['space_seat_id' => 'Choose the seat: in this zone each seat is proposed on its own.']),
            $seats === null => throw ValidationException::withMessages(['seats' => 'Enter how many seats are proposed.']),
            $seats > $capacity => throw ValidationException::withMessages(['seats' => 'This zone offers '.$capacity.' '.str('seat')->plural($capacity).'.']),
            default => $seats,
        };
    }
}
