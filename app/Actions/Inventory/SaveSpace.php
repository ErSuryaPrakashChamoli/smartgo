<?php

namespace App\Actions\Inventory;

use App\Actions\Properties\SaveProperty;
use App\Enums\Access\Capability;
use App\Enums\Inventory\InventoryMode;
use App\Events\Inventory\SpaceAvailabilityChanged;
use App\Models\Offering;
use App\Models\Product;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceSeat;
use App\Models\SpaceType;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use App\Services\Inventory\SpaceAvailability;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Adds a space to a property's floor, or changes one (FR-PROP-2, FR-PROP-3, BR-INV-1), with the reason
 * for the change. Only facts staff enter are stored. Staff with `inventory.manage` for the property may
 * do it (SECURITY_MODEL §3.1 "Spaces and seats"); marketing never can (BR-SOT-3).
 *
 * Rules: the space type must allow the inventory mode, and every category it is sold as must be one of
 * the property's active offerings whose category accepts both that mode and the space type
 * (BR-PRODCFG-2, ERD §4.1; the type list is checked where the database has it, OD-153). The code is permanent
 * (BR-INV-2). A per-seat or hybrid zone gets one seat per place when added; afterwards its capacity is
 * its active seats, changed through its seats. Changing an existing space locks its row first
 * (ADR-0004): the mode changes only with no current or future allocation (BR-INV-3), and a lower
 * capacity is refused, with the allocations it would break, when they no longer fit (BR-INV-4).
 * Status changes go through ChangeSpaceStatus.
 */
class SaveSpace
{
    public function __construct(
        private RecordInventoryActivity $recordInventoryActivity,
        private SpaceAvailability $spaceAvailability,
    ) {}

    /**
     * The audited fields of a space.
     *
     * @var list<string>
     */
    private const array AUDITED = ['code', 'name', 'floor_id', 'space_type_id', 'inventory_mode', 'capacity_seats', 'area_sqft', 'is_publicly_listable'];

    /**
     * @param  array<string, mixed>  $data  floor_id, space_type_id, code (new spaces), name, inventory_mode, capacity_seats, area_sqft, is_publicly_listable, offering_ids, reason
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Property $property, ?Space $space, array $data): Space
    {
        if (($space !== null && $space->property_id !== $property->getKey()) || ! $user->hasCapabilityForProperty(Capability::ManageInventory, $property)) {
            throw new AuthorizationException('You cannot change the spaces of this property.');
        }

        $validated = $this->validate($property, $space, $data);
        $mode = InventoryMode::from($validated['inventory_mode']);

        return DB::transaction(function () use ($user, $property, $space, $validated, $mode): Space {
            $space = $space === null ? new Space : Space::query()->lockForUpdate()->findOrFail($space->getKey());
            $before = $space->exists ? $this->audited($space) : null;
            $offeringsBefore = $space->exists ? $space->offerings()->pluck('offerings.id')->sort()->values()->all() : [];
            $today = BusinessDay::today();

            if ($space->exists && $space->inventory_mode !== $mode && ($commitments = $this->spaceAvailability->commitmentsFrom($space, $today))->isNotEmpty()) {
                throw ValidationException::withMessages(['inventory_mode' => 'The way this space is counted can change only when nothing is allocated in it now or later (BR-INV-3). Allocated: '.$commitments->map->summary()->implode('; ').'.']);
            }

            $capacity = (int) $validated['capacity_seats'];

            if ($space->exists && ! $mode->hasSeats() && $space->inventory_mode === $mode && $capacity < $space->capacity_seats
                && ($over = $this->spaceAvailability->overCommittedAllocations($space, $capacity, $today))->isNotEmpty()) {
                throw ValidationException::withMessages(['capacity_seats' => 'With '.$capacity.' '.str('seat')->plural($capacity).' these allocations would no longer fit; move or end them first (BR-INV-4): '.$over->map->summary()->implode('; ').'.']);
            }

            $space->forceFill([
                ...Arr::only($validated, ['floor_id', 'space_type_id', 'name', 'area_sqft']),
                ...($space->exists ? [] : ['code' => $validated['code'], 'property_id' => $property->getKey()]),
                'inventory_mode' => $mode,
                'is_publicly_listable' => (bool) ($validated['is_publicly_listable'] ?? false),
                'capacity_seats' => $mode->hasSeats() && $space->exists && $space->seats()->exists() ? $space->capacity_seats : $capacity,
            ]);

            try {
                $space->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['code' => 'This code is already used by another space, now or before. Codes are permanent.']);
            }

            if ($mode->hasSeats() && ! $space->seats()->exists()) {
                foreach (range(1, $capacity) as $number) {
                    (new SpaceSeat)->forceFill(['space_id' => $space->getKey(), 'label' => (string) $number, 'is_active' => true])->save();
                }
            }

            if ($mode->hasSeats()) {
                $space->forceFill(['capacity_seats' => max(1, $space->seats()->where('is_active', true)->count())])->save();
            }

            $offeringIds = array_values(array_unique(array_map('intval', $validated['offering_ids'] ?? [])));
            sort($offeringIds);
            $space->offerings()->sync($offeringIds);

            $after = $this->audited($space);

            if ($before === null || $before !== $after || $offeringsBefore !== $offeringIds) {
                $this->recordInventoryActivity->handle($space, $user, $before === null ? 'space_added' : 'space_changed', $before === null ? 'Space added' : 'Space changed', $validated['reason'], [
                    'property' => $property->code,
                    'old' => $before === null ? null : [...$before, 'offering_ids' => $offeringsBefore],
                    'attributes' => [...$after, 'offering_ids' => $offeringIds],
                ]);
                SpaceAvailabilityChanged::dispatch($space, $before === null ? 'added' : 'changed');
            }

            return $space->fresh(['seats', 'offerings']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(Property $property, ?Space $space, array $data): array
    {
        $type = SpaceType::query()->find($data['space_type_id'] ?? null);
        $mode = InventoryMode::tryFrom((string) ($data['inventory_mode'] ?? ''));

        $validator = Validator::make($data, [
            'floor_id' => ['required', 'integer', Rule::exists('floors', 'id')->where('property_id', $property->getKey())],
            'space_type_id' => ['required', 'integer', Rule::exists('space_types', 'id')],
            'code' => $space === null ? ['required', 'string', 'max:30', 'regex:'.SaveProperty::CODE_PATTERN, Rule::unique('spaces', 'code')] : ['prohibited'],
            'name' => ['nullable', 'string', 'max:120', 'not_regex:/[<>]/'],
            'inventory_mode' => ['required', Rule::enum(InventoryMode::class)],
            'capacity_seats' => ['required', 'integer', 'between:1,2000'],
            'area_sqft' => ['nullable', 'integer', 'between:1,1000000'],
            'is_publicly_listable' => ['boolean'],
            'offering_ids' => ['nullable', 'array'],
            'offering_ids.*' => ['integer', Rule::exists('offerings', 'id')->where('property_id', $property->getKey())->where('is_active', true)],
            'reason' => ['required', 'string', 'max:300', 'not_regex:/[<>]/'],
        ], [
            'floor_id.exists' => 'Choose one of this property\'s floors.',
            'code.regex' => 'Use capital letters, numbers and hyphens, for example A70-C01.',
            'code.unique' => 'This code is already used by another space, now or before. Codes are permanent.',
            'code.prohibited' => 'A space\'s code is permanent and cannot be changed.',
            'offering_ids.*.exists' => 'Choose workspace categories this property offers.',
            'capacity_seats.between' => 'Enter from 1 to 2,000 seats.',
            '*.not_regex' => 'Use plain text: < and > are not allowed.',
        ], ['floor_id' => 'floor', 'space_type_id' => 'space type', 'capacity_seats' => 'seats', 'area_sqft' => 'area']);

        $validator->after(function ($validator) use ($type, $mode, $data): void {
            if ($type === null || $mode === null) {
                return;
            }

            if (! $type->allowsInventoryMode($mode)) {
                $validator->errors()->add('inventory_mode', 'A '.strtolower($type->name).' cannot be counted as "'.strtolower($mode->label()).'".');
            }

            $offerings = Offering::query()->with(Product::hasSpaceTypes() ? 'product.spaceTypes' : 'product')->whereKey(array_map('intval', Arr::wrap($data['offering_ids'] ?? [])))->get();
            $incompatible = $offerings->reject(fn (Offering $offering): bool => $offering->product->allowsInventoryMode($mode));
            $otherType = $offerings->reject(fn (Offering $offering): bool => $offering->product->acceptsSpaceType($type));

            if ($incompatible->isNotEmpty()) {
                $validator->errors()->add('offering_ids', 'A space counted as "'.strtolower($mode->label()).'" cannot be sold as '.$incompatible->map(fn (Offering $offering): string => $offering->product->name)->implode(', ').' (BR-PRODCFG-2).');
            }

            if ($otherType->isNotEmpty()) {
                $validator->errors()->add('offering_ids', 'A '.strtolower($type->name).' cannot be sold as '.$otherType->map(fn (Offering $offering): string => $offering->product->name)->implode(', ').': that category does not accept this space type (BR-PRODCFG-2).');
            }
        });

        return $validator->validate();
    }

    /**
     * @return array<string, mixed>
     */
    private function audited(Space $space): array
    {
        return [...$space->only(self::AUDITED), 'inventory_mode' => $space->inventory_mode->value];
    }
}
