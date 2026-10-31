<?php

namespace App\Actions\Inventory;

use App\Enums\Access\Capability;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Property;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Adds or changes a floor of one of a property's buildings (FR-PROP-1, BR-PROP-6), from facts staff
 * enter: no floor is assumed (OD-30, OD-32). Adding a floor needs `properties.manage` for every
 * property; a Property Manager may correct the label and level of their own property's floors but not
 * add one (SECURITY_MODEL §3.1, "edit only, no create/delete"). Audited with the reason given.
 */
class SaveFloor
{
    public function __construct(private RecordInventoryActivity $recordInventoryActivity) {}

    /**
     * @param  array<string, mixed>  $data  building_id, building_level, label, reason
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Property $property, ?Floor $floor, array $data): Floor
    {
        $allowed = $floor === null
            ? $user->hasCapabilityForEveryProperty(Capability::ManageProperties)
            : $floor->property_id === $property->getKey() && $user->hasCapabilityForProperty(Capability::ManageProperties, $property);

        if (! $allowed) {
            throw new AuthorizationException($floor === null ? 'Only operations staff can add floors.' : 'You cannot change this floor.');
        }

        $validated = Validator::make($data, [
            'building_id' => ['required', 'integer', Rule::exists('buildings', 'id')->where('property_id', $property->getKey())],
            'building_level' => ['required', 'integer', 'between:-5,200',
                Rule::unique('floors', 'building_level')->where('building_id', $data['building_id'] ?? null)->ignore($floor?->getKey())],
            'label' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'reason' => ['required', 'string', 'max:300', 'not_regex:/[<>]/'],
        ], [
            'building_id.exists' => 'Choose one of this property\'s buildings.',
            'building_level.unique' => 'This building already has a floor at this level.',
            'building_level.between' => 'Use a level from -5 (basement) to 200; 0 is the ground floor.',
            '*.not_regex' => 'Use plain text: < and > are not allowed.',
        ], ['building_id' => 'building', 'building_level' => 'level'])->validate();

        return DB::transaction(function () use ($user, $property, $floor, $validated): Floor {
            Building::query()->whereKey($validated['building_id'])->lockForUpdate()->sole();
            $floor = $floor === null ? new Floor : Floor::query()->lockForUpdate()->findOrFail($floor->getKey());
            $before = $floor->exists ? $floor->only(['building_id', 'building_level', 'label']) : null;

            $floor->forceFill([
                'property_id' => $property->getKey(),
                'building_id' => (int) $validated['building_id'],
                'building_level' => (int) $validated['building_level'],
                'label' => $validated['label'],
            ])->save();

            $this->recordInventoryActivity->handle($floor, $user, $before === null ? 'floor_added' : 'floor_changed', $before === null ? 'Floor added' : 'Floor changed', $validated['reason'], [
                'property' => $property->code,
                'old' => $before,
                'attributes' => $floor->only(['building_id', 'building_level', 'label']),
            ]);

            return $floor;
        });
    }
}
