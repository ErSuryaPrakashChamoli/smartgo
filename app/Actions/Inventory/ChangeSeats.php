<?php

namespace App\Actions\Inventory;

use App\Enums\Access\Capability;
use App\Events\Inventory\SpaceAvailabilityChanged;
use App\Models\Space;
use App\Models\SpaceSeat;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use App\Services\Inventory\SpaceAvailability;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds seats to a per-seat or hybrid zone, or takes a seat out of use or back into it (FR-PROP-2,
 * BR-INV-1), with a reason. Every change locks the zone's row first, never only the seat (ADR-0004).
 * Taking a seat out of use is refused, with the allocations concerned, when an allocation uses that
 * seat now or later, or when the zone's remaining seats could no longer hold what is allocated
 * (BR-INV-4, BR-AV-3d). The zone's capacity follows its active seats.
 */
class ChangeSeats
{
    public const int MAX_NEW_SEATS = 200;

    public function __construct(
        private RecordInventoryActivity $recordInventoryActivity,
        private SpaceAvailability $spaceAvailability,
    ) {}

    /**
     * @param  'add'|'deactivate'|'activate'  $change
     * @param  array{count?: int|string|null, seat_id?: int|string|null, reason?: string|null}  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Space $space, string $change, array $data): Space
    {
        if (! $user->hasCapabilityForProperty(Capability::ManageInventory, $space->property)) {
            throw new AuthorizationException('You cannot change the seats of this property\'s spaces.');
        }

        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '' || mb_strlen($reason) > 300 || preg_match('/[<>]/', $reason) === 1) {
            throw ValidationException::withMessages(['reason' => 'Give the reason for the change in plain text, up to 300 characters.']);
        }

        return DB::transaction(function () use ($user, $space, $change, $data, $reason): Space {
            $space = Space::query()->lockForUpdate()->findOrFail($space->getKey());

            if (! $space->inventory_mode->hasSeats()) {
                throw ValidationException::withMessages(['seats' => 'Only zones whose seats are assigned individually have seats.']);
            }

            $properties = match ($change) {
                'add' => $this->add($space, (int) ($data['count'] ?? 0)),
                'deactivate', 'activate' => $this->setActive($space, (int) ($data['seat_id'] ?? 0), $change === 'activate'),
            };

            $space->forceFill(['capacity_seats' => max(1, $space->seats()->where('is_active', true)->count())])->save();

            $this->recordInventoryActivity->handle($space, $user, 'space_seats_changed', match ($change) {
                'add' => 'Seats added',
                'deactivate' => 'Seat taken out of use',
                'activate' => 'Seat back in use',
            }, $reason, ['property' => $space->property->code, ...$properties]);
            SpaceAvailabilityChanged::dispatch($space, 'seats');

            return $space->fresh(['seats']);
        });
    }

    /**
     * Adds seats numbered after the highest numbered seat.
     *
     * @return array<string, mixed>
     */
    private function add(Space $space, int $count): array
    {
        if ($count < 1 || $count > self::MAX_NEW_SEATS) {
            throw ValidationException::withMessages(['count' => 'Add from 1 to '.self::MAX_NEW_SEATS.' seats at a time.']);
        }

        $labels = $space->seats()->pluck('label');
        $next = (int) $labels->filter(fn (string $label): bool => ctype_digit($label))->map(fn (string $label): int => (int) $label)->max() + 1;
        $added = [];

        foreach (range($next, $next + $count - 1) as $number) {
            (new SpaceSeat)->forceFill(['space_id' => $space->getKey(), 'label' => (string) $number, 'is_active' => true])->save();
            $added[] = (string) $number;
        }

        return ['added' => $added];
    }

    /**
     * @return array<string, mixed>
     */
    private function setActive(Space $space, int $seatId, bool $active): array
    {
        $seat = SpaceSeat::query()->where('space_id', $space->getKey())->lockForUpdate()->find($seatId)
            ?? throw ValidationException::withMessages(['seat_id' => 'Choose one of this zone\'s seats.']);

        if ($seat->is_active === $active) {
            throw ValidationException::withMessages(['seat_id' => 'Seat '.$seat->label.' is already '.($active ? 'in use' : 'out of use').'.']);
        }

        if (! $active) {
            $remaining = $space->seats()->where('is_active', true)->whereKeyNot($seat->getKey())->count();
            $over = $this->spaceAvailability->overCommittedAllocations($space, $remaining, BusinessDay::today(), [$seat->getKey()]);

            if ($over->isNotEmpty()) {
                throw ValidationException::withMessages(['seat_id' => 'Seat '.$seat->label.' cannot be taken out of use while these allocations need it; move or end them first (BR-INV-4): '.$over->map->summary()->implode('; ').'.']);
            }
        }

        $seat->forceFill(['is_active' => $active])->save();

        return ['seat' => $seat->label];
    }
}
