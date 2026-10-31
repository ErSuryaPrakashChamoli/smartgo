<?php

namespace App\Services\Inventory\Import;

use App\Enums\Inventory\SpaceStatus;
use App\Models\Amenity;
use App\Models\Building;
use App\Models\Floor;
use App\Models\InventoryImport;
use App\Models\MicroMarket;
use App\Models\Offering;
use App\Models\Product;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\SpaceSeat;
use App\Models\SpaceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Turns validated SG-INV records into changes (INVENTORY_IMPORT_SPEC §3): a preview of what is new,
 * changed, unchanged and "not in file" per sheet, with field-level before and after, and the effect on
 * availability; and, at commit, the writes. Upserts go by the permanent natural keys, so importing the same
 * content again changes nothing; nothing is ever deleted (missing records are reported, never removed).
 * Every created or changed record is audited with the import's number.
 *
 * The preview's fingerprint covers the planned changes, so a commit can tell whether anything changed in
 * SmartGro since the preview was approved.
 */
class InventoryImporter
{
    /**
     * @param  array<string, mixed>  $records  WorkbookValidator records without errors
     * @return array{counts: array<string, array{new: int, updated: int, unchanged: int, not_in_file: int}>, changes: list<array{sheet: string, key: string, kind: string, fields: array<string, array{0: mixed, 1: mixed}>}>, impact: list<string>, notes: list<string>, fingerprint: string}
     */
    public function plan(array $records): array
    {
        $changes = [];
        $counts = [];
        $impact = [];

        foreach ($this->rows($records) as $sheet => $items) {
            $counts[$sheet] = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'not_in_file' => 0];

            foreach ($items as $key => [$model, $values]) {
                $values = array_filter($values, fn (string $field): bool => ! str_starts_with($field, '@'), ARRAY_FILTER_USE_KEY);
                $fields = collect($values)->filter(fn (mixed $value, string $field): bool => $model === null || $this->normalise($model->getAttribute($field)) !== $this->normalise($value))
                    ->map(fn (mixed $value, string $field): array => [$model === null ? null : $this->normalise($model->getAttribute($field)), $this->normalise($value)])->all();
                $kind = $model === null ? 'new' : ($fields === [] ? 'unchanged' : 'updated');
                $counts[$sheet][$kind]++;

                if ($kind !== 'unchanged') {
                    $changes[] = ['sheet' => $sheet, 'key' => (string) $key, 'kind' => $kind, 'fields' => $fields];
                }

                if ($sheet === 'Spaces' && isset($fields['operational_status'])) {
                    $impact[] = 'Space '.$key.($values['operational_status'] === SpaceStatus::Active->value ? ' becomes usable.' : ' will stop being usable ('.Str::lower(SpaceStatus::from($values['operational_status'])->label()).').');
                }
            }
        }

        $propertyCodes = array_keys($records['Property'] ?? []);
        $counts['Spaces']['not_in_file'] = Space::query()->whereHas('property', fn ($query) => $query->whereIn('code', $propertyCodes))->whereNotIn('code', array_keys($records['Spaces'] ?? []))->count();
        $notes = collect($records['Floors'] ?? [])->filter(fn (array $floor): bool => filled($floor['plan_file']))->isNotEmpty()
            ? ['Floor plan file names are not stored: floor plans are uploaded separately once that screen exists (C-6.4).'] : [];

        usort($changes, fn (array $a, array $b): int => [$a['sheet'], $a['key']] <=> [$b['sheet'], $b['key']]);

        return ['counts' => $counts, 'changes' => $changes, 'impact' => $impact, 'notes' => $notes, 'fingerprint' => hash('sha256', json_encode([$changes, $counts], JSON_THROW_ON_ERROR))];
    }

    /**
     * Writes the records, inside the caller's transaction, as $user, and returns the codes of the spaces
     * whose availability may have changed.
     *
     * @param  array<string, mixed>  $records
     * @return list<int> ids of affected spaces
     */
    public function apply(array $records, User $user, InventoryImport $import): array
    {
        $affected = [];
        $saved = [];

        foreach ($this->rows($records) as $sheet => $items) {
            foreach ($items as $key => [$model, $values]) {
                $model ??= $this->newModel($sheet);
                $compared = array_filter($values, fn (string $field): bool => ! str_starts_with($field, '@'), ARRAY_FILTER_USE_KEY);
                $before = $model->exists ? collect($compared)->mapWithKeys(fn (mixed $value, string $field): array => [$field => $this->normalise($model->getAttribute($field))])->all() : null;
                $model->forceFill($this->resolve($sheet, $values, $saved, $user));
                $isDirty = $before === null || $before !== collect($compared)->map(fn (mixed $value): mixed => $this->normalise($value))->all();
                $model->save();
                $saved[$sheet][$key] = $model;

                if ($sheet === 'Spaces') {
                    $this->spaceLinks($model, $records['Spaces'][$key], $saved);
                }

                if ($sheet === 'Property') {
                    $model->profile()->firstOrCreate([]);
                }

                if ($isDirty) {
                    activity('inventory')->performedOn($model)->causedBy($user)->event($before === null ? 'imported_created' : 'imported_updated')
                        ->withProperties(['import' => $import->getKey(), 'old' => $before, 'attributes' => collect($compared)->map(fn (mixed $value): mixed => $this->normalise($value))->all()])
                        ->log($before === null ? 'Created by inventory import' : 'Changed by inventory import');

                    if (in_array($sheet, ['Spaces', 'Seats', 'Occupancy'], true)) {
                        $affected[] = $sheet === 'Spaces' ? $model->getKey() : $model->getAttribute('space_id');
                    }
                }
            }
        }

        return array_values(array_unique($affected));
    }

    /**
     * Each sheet's records with the existing model (or null) and the values to write, in writing order.
     * References to records of the same import are resolved when writing.
     *
     * @param  array<string, mixed>  $records
     * @return array<string, array<string, array{0: Model|null, 1: array<string, mixed>}>>
     */
    private function rows(array $records): array
    {
        $properties = Property::query()->whereIn('code', array_keys($records['Property'] ?? []))->get()->keyBy('code');
        $hours = collect($records['Hours'] ?? [])->groupBy('property');
        $rows = ['Amenities' => [], 'Property' => [], 'Buildings' => [], 'Floors' => [], 'Spaces' => [], 'Seats' => [], 'Occupancy' => []];

        foreach ($records['Amenities'] ?? [] as $amenity) {
            $rows['Amenities'][$amenity['name']] = [Amenity::query()->where('name', $amenity['name'])->first(), ['name' => $amenity['name'], 'slug' => Str::slug($amenity['name']), 'category' => $amenity['category'], 'applies_to' => $amenity['applies_to']]];
        }

        foreach ($records['Property'] ?? [] as $code => $property) {
            $days = $hours->get($code, collect())->keyBy('day');
            $operatingHours = $days->isEmpty() ? null : collect(Property::WEEKDAYS)->mapWithKeys(fn (string $label, string $day): array => [$day => filled($days[$day]['opens'] ?? null) ? ['opens' => $days[$day]['opens'], 'closes' => $days[$day]['closes']] : null])->all();
            $rows['Property'][$code] = [$properties[$code] ?? null, [
                'code' => $code, 'name' => $property['name'], 'slug' => $properties[$code]->slug ?? Str::slug($code), 'city_id' => $property['city_id'],
                '@micro_market' => $property['micro_market'], 'operating_model' => $property['operating_model'], 'operational_status' => $property['operational_status'],
                'opens_on' => $property['opens_on'], 'operating_hours' => $operatingHours,
            ]];
        }

        foreach ($records['Buildings'] ?? [] as $code => $building) {
            $rows['Buildings'][$code] = [Building::query()->where('code', $code)->first(), [
                'code' => $code, '@property' => $building['property'], 'name' => $building['name'], 'address_line_1' => $building['address_line_1'],
                'address_line_2' => $building['address_line_2'], 'locality' => $building['locality'], 'postal_code' => $building['postal_code'], 'state' => $building['state'],
                'country_code' => $building['country_code'], 'latitude' => $building['latitude'], 'longitude' => $building['longitude'], 'is_primary' => $building['is_primary'],
            ]];
        }

        foreach ($records['Floors'] ?? [] as $key => $floor) {
            $rows['Floors'][$key] = [Floor::query()->whereHas('building', fn ($query) => $query->where('code', $floor['building']))->where('building_level', $floor['level'])->first(), [
                '@building' => $floor['building'], 'building_level' => $floor['level'], 'label' => $floor['label'], 'sort_order' => $floor['sort_order'],
            ]];
        }

        $types = SpaceType::query()->pluck('id', 'key');

        foreach ($records['Spaces'] ?? [] as $code => $space) {
            $rows['Spaces'][$code] = [Space::withTrashed()->where('code', $code)->first(), [
                'code' => $code, '@property' => $space['property'], '@floor' => $space['building'].'|'.$space['level'], 'space_type_id' => $types[$space['type']] ?? null,
                'name' => $space['name'], 'inventory_mode' => $space['mode'], 'capacity_seats' => $space['capacity'], 'area_sqft' => $space['area'],
                'operational_status' => $space['status'], 'is_publicly_listable' => $space['listable'], 'turnaround_minutes' => $space['turnaround'],
                'min_booking_minutes' => $space['min_booking'], 'max_booking_minutes' => $space['max_booking'],
            ]];
        }

        foreach ($records['Seats'] ?? [] as $key => $seat) {
            $rows['Seats'][$key] = [SpaceSeat::query()->whereHas('space', fn ($query) => $query->where('code', $seat['space']))->where('label', $seat['label'])->first(), [
                '@space' => $seat['space'], 'label' => $seat['label'], 'is_active' => $seat['active'],
            ]];
        }

        foreach ($records['Occupancy'] ?? [] as $key => $agreement) {
            $rows['Occupancy'][$key] = [SpaceAllocation::query()->whereHas('space', fn ($query) => $query->where('code', $agreement['space']))
                ->where('agreement_reference', $agreement['reference'])->whereDate('starts_on', $agreement['starts_on'])
                ->when($agreement['seat'] === null, fn ($query) => $query->whereNull('space_seat_id'), fn ($query) => $query->whereHas('seat', fn ($seat) => $seat->where('label', $agreement['seat'])))->first(), [
                    '@space' => $agreement['space'], '@seat' => $agreement['seat'], 'agreement_reference' => $agreement['reference'], 'seats_allocated' => $agreement['seats'],
                    'starts_on' => $agreement['starts_on'], 'ends_on' => $agreement['ends_on'], 'notice_given_on' => $agreement['notice_given_on'], 'status' => $agreement['status'],
                ]];
        }

        return $rows;
    }

    /**
     * The values to write, with references ("@…") turned into ids.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, array<string, Model>>  $saved
     * @return array<string, mixed>
     */
    private function resolve(string $sheet, array $values, array $saved, User $user): array
    {
        $property = fn (string $code): Property => $saved['Property'][$code] ?? Property::query()->where('code', $code)->sole();
        $space = fn (string $code): Space => $saved['Spaces'][$code] ?? Space::withTrashed()->where('code', $code)->sole();
        $resolved = array_filter($values, fn (string $field): bool => ! str_starts_with($field, '@'), ARRAY_FILTER_USE_KEY);

        return match ($sheet) {
            'Property' => [...$resolved, 'micro_market_id' => filled($values['@micro_market']) ? $this->sector($values['city_id'], $values['@micro_market']) : null],
            'Buildings' => [...$resolved, 'property_id' => $property($values['@property'])->getKey(), 'city_id' => $property($values['@property'])->city_id],
            'Floors' => [...$resolved, ...(function () use ($values, $saved): array {
                $building = $saved['Buildings'][$values['@building']] ?? Building::query()->where('code', $values['@building'])->sole();

                return ['building_id' => $building->getKey(), 'property_id' => $building->property_id];
            })()],
            'Spaces' => [...$resolved, 'property_id' => $property($values['@property'])->getKey(), 'floor_id' => ($saved['Floors'][$values['@floor']] ?? Floor::query()->whereHas('building', fn ($query) => $query->where('code', explode('|', $values['@floor'])[0]))->where('building_level', (int) explode('|', $values['@floor'])[1])->sole())->getKey()],
            'Seats' => [...$resolved, 'space_id' => $space($values['@space'])->getKey()],
            'Occupancy' => [...$resolved, 'space_id' => $space($values['@space'])->getKey(), 'space_seat_id' => $values['@seat'] === null ? null
                : ($saved['Seats'][$values['@space'].'|'.$values['@seat']] ?? SpaceSeat::query()->where('space_id', $space($values['@space'])->getKey())->where('label', $values['@seat'])->sole())->getKey(),
                'source' => 'import', ...($sheet === 'Occupancy' ? ['created_by' => $user->getKey()] : [])],
            default => $resolved,
        };
    }

    /**
     * A space's categories (creating the property's offering, with an empty profile, when it has none
     * yet) and amenities, as the file gives them.
     *
     * @param  array<string, mixed>  $record
     * @param  array<string, array<string, Model>>  $saved
     */
    private function spaceLinks(Space $space, array $record, array $saved): void
    {
        $offeringIds = collect($record['categories'])->map(function (string $key) use ($space): int {
            $productId = Product::query()->where('key', $key)->value('id');
            $offering = Offering::query()->where('property_id', $space->property_id)->where('product_id', $productId)->first()
                ?? Offering::query()->forceCreate(['property_id' => $space->property_id, 'product_id' => $productId, 'is_active' => true]);

            if (! $offering->is_active) {
                $offering->forceFill(['is_active' => true])->save();
            }

            $offering->profile()->firstOrCreate([]);

            return $offering->getKey();
        });

        $space->offerings()->sync($offeringIds->all());
        $space->amenities()->sync(collect($record['amenities'])->map(fn (string $name): int => ($saved['Amenities'][$name] ?? Amenity::query()->where('name', $name)->sole())->getKey())->all());
    }

    /**
     * The city's sector with this name, added when it is new (a sector is how customers search, e.g.
     * "Sector 62").
     */
    private function sector(int $cityId, string $name): int
    {
        return (MicroMarket::query()->where('city_id', $cityId)->where('slug', Str::slug($name))->first()
            ?? MicroMarket::query()->forceCreate(['city_id' => $cityId, 'slug' => Str::slug($name), 'name' => $name]))->getKey();
    }

    private function newModel(string $sheet): Model
    {
        return match ($sheet) {
            'Amenities' => new Amenity,
            'Property' => new Property,
            'Buildings' => new Building,
            'Floors' => new Floor,
            'Spaces' => new Space,
            'Seats' => new SpaceSeat,
            'Occupancy' => new SpaceAllocation,
        };
    }

    /**
     * Values as they compare and are recorded: enums by value, dates as YYYY-MM-DD, decimals as numbers.
     */
    private function normalise(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            is_numeric($value) && is_string($value) => $value + 0,
            is_float($value) => round($value, 6),
            is_array($value) => $this->sortedKeys($value),
            default => $value,
        };
    }

    /**
     * Arrays compared regardless of key order: MySQL stores JSON objects with their keys reordered.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function sortedKeys(array $value): array
    {
        ksort($value);

        return array_map(fn (mixed $item): mixed => is_array($item) ? $this->sortedKeys($item) : $item, $value);
    }
}
