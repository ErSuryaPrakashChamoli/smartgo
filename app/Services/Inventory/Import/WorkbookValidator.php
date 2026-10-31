<?php

namespace App\Services\Inventory\Import;

use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use App\Enums\Inventory\AllocationStatus;
use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Models\Amenity;
use App\Models\Building;
use App\Models\City;
use App\Models\Floor;
use App\Models\Product;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\SpaceSeat;
use App\Models\SpaceType;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Str;
use Locale;

/**
 * Checks an SG-INV-1.3 workbook against INVENTORY_IMPORT_SPEC §4, in the spec's stages: structure
 * (V-001 to V-004), each field (V-101 to V-117), keys and references (V-120 to V-135), then occupancy and
 * business rules (V-140 to W-170) and the synthetic-data guard (V-180). A row that fails a field or
 * reference rule is left out of the later, aggregate checks, so one mistake gives one message. V-117 is
 * checked only once V-116 passes.
 *
 * It returns every finding and, for the rows without errors, the normalised records the import would
 * write. It reads the database for references (cities, categories, space types, existing codes, seats and
 * allocations, staff) and never writes. Interpretations where the template and the database differ are
 * recorded in DECISION_REGISTER OD-128 (f).
 */
class WorkbookValidator
{
    public const int MAX_BYTES = 10 * 1024 * 1024;

    public const int MAX_ROWS = 20000;

    /**
     * The data sheets and their column keys (row 1), in template order (§2).
     *
     * @var array<string, list<string>>
     */
    public const array COLUMNS = [
        'Property' => ['property_code', 'property_name', 'city', 'micro_market', 'operating_model', 'operational_status', 'opens_on', 'timezone', 'property_manager_email'],
        'Buildings' => ['property_code', 'building_code', 'building_name', 'address_line_1', 'address_line_2', 'locality', 'postal_code', 'state', 'country_code', 'latitude', 'longitude', 'is_primary'],
        'Hours' => ['property_code', 'day_of_week', 'is_closed', 'opens_at', 'closes_at'],
        'Floors' => ['property_code', 'building_code', 'building_level', 'floor_label', 'sort_order', 'floor_plan_file'],
        'Spaces' => ['property_code', 'building_code', 'building_level', 'space_code', 'space_name', 'space_type', 'inventory_mode', 'capacity_seats', 'area_sqft', 'operational_status', 'publicly_listable', 'sellable_as', 'amenities', 'turnaround_minutes', 'min_booking_minutes', 'max_booking_minutes', 'notes'],
        'Seats' => ['space_code', 'seat_label', 'is_active'],
        'Occupancy' => ['space_code', 'seat_label', 'contract_reference', 'seats_allocated', 'starts_on', 'ends_on', 'notice_given_on', 'status'],
        'Amenities' => ['amenity_name', 'category', 'applies_to'],
    ];

    /**
     * Columns the template marks required (green headers). `locality` is required too, because a
     * building's address needs it in the back office (OD-128 f).
     *
     * @var array<string, list<string>>
     */
    private const array REQUIRED = [
        'Property' => ['property_code', 'property_name', 'city', 'operating_model', 'operational_status'],
        'Buildings' => ['property_code', 'building_code', 'address_line_1', 'locality', 'state', 'country_code', 'is_primary'],
        'Hours' => ['property_code', 'day_of_week', 'is_closed'],
        'Floors' => ['property_code', 'building_code', 'building_level', 'floor_label'],
        'Spaces' => ['property_code', 'building_code', 'building_level', 'space_code', 'space_name', 'space_type', 'inventory_mode', 'capacity_seats', 'operational_status', 'publicly_listable', 'sellable_as'],
        'Seats' => ['space_code', 'seat_label', 'is_active'],
        'Occupancy' => ['space_code', 'contract_reference', 'seats_allocated', 'starts_on', 'status'],
        'Amenities' => ['amenity_name', 'category', 'applies_to'],
    ];

    /**
     * Text limits (V-103): the spec's, or the database column's where that is shorter.
     *
     * @var array<string, int>
     */
    private const array LENGTHS = [
        'property_name' => 80, 'micro_market' => 80, 'building_name' => 80, 'address_line_1' => 120, 'address_line_2' => 120,
        'locality' => 80, 'state' => 60, 'floor_label' => 120, 'space_name' => 120, 'notes' => 1000, 'contract_reference' => 60,
        'amenity_name' => 120, 'seat_label' => 20, 'floor_plan_file' => 190, 'city' => 80,
    ];

    /**
     * Dropdown labels (§2, the Lists sheet) → stored keys. Keys themselves are accepted too.
     *
     * @var array<string, array<string, string>>
     */
    private const array LISTS = [
        'operating_model' => ['owned & operated' => 'owned_operated', 'leased & operated' => 'leased_operated'],
        'property_status' => ['planned' => 'planned', 'pre-opening' => 'pre_opening', 'open' => 'open', 'temporarily closed' => 'temporarily_closed', 'closed' => 'closed'],
        'space_type' => ['private cabin' => 'private_cabin', 'dedicated desk zone' => 'desk_zone_dedicated', 'flexible seating zone' => 'desk_zone_flexible', 'meeting room' => 'meeting_room', 'conference / training room' => 'conference_training_room', 'office suite' => 'office_suite'],
        'inventory_mode' => ['whole space' => 'exclusive', 'per seat' => 'per_seat', 'pooled' => 'pooled', 'hybrid (seats + pool)' => 'hybrid', 'hybrid' => 'hybrid'],
        'space_status' => ['active' => 'active', 'maintenance' => 'maintenance', 'out of service' => 'out_of_service', 'decommissioned' => 'decommissioned'],
        'allocation_status' => ['reserved' => 'reserved', 'active' => 'active', 'notice period' => 'notice_period', 'ended' => 'ended'],
        'weekday' => ['monday' => 'mon', 'tuesday' => 'tue', 'wednesday' => 'wed', 'thursday' => 'thu', 'friday' => 'fri', 'saturday' => 'sat', 'sunday' => 'sun'],
        'category' => ['private cabins / private offices' => 'private_office', 'dedicated desks' => 'dedicated_desk', 'hot desks / flexible seating' => 'hot_desk', 'meeting rooms' => 'meeting_room', 'conference and training rooms' => 'conference_room', 'virtual office / business address' => 'virtual_office', 'managed offices for enterprises' => 'managed_office'],
        'amenity_category' => ['workspace' => 'workspace', 'technology' => 'technology', 'wellbeing' => 'wellbeing', 'hospitality' => 'hospitality', 'access' => 'access', 'building' => 'building', 'accessibility' => 'accessibility'],
        'amenity_scope' => ['location' => 'location', 'space' => 'space', 'both' => 'both'],
    ];

    /** Categories booked by time (V-115) and sold on term agreements (V-151). */
    private const array TIME_BOOKED = ['meeting_room', 'conference_room'];

    private const array TERM_AGREEMENT = ['private_office', 'dedicated_desk', 'hot_desk', 'managed_office'];

    private const string CODE_PATTERN = '/^[A-Z0-9][A-Z0-9-]{0,38}[A-Z0-9]$/';

    /** @var list<array{sheet: string, row: int|null, column: string|null, rule: string, severity: string, message: string, value: string|null}> */
    private array $issues = [];

    /** @var array<string, array<int, true>> rows (by sheet) with an error */
    private array $failed = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $rows = [];

    /**
     * @param  array{sheets: array<string, array{headers: list<string>, rows: array<int, list<mixed>>}>, meta: array<string, string>}|null  $workbook
     * @return array{issues: list<array{sheet: string, row: int|null, column: string|null, rule: string, severity: string, message: string, value: string|null}>, records: array<string, mixed>}
     */
    public function validate(?array $workbook, int $bytes, User $uploader): array
    {
        $this->issues = [];
        $this->failed = [];
        $this->rows = [];

        if (! $this->structure($workbook, $bytes)) {
            return ['issues' => $this->issues, 'records' => []];
        }

        $context = $this->context();
        $records = [];
        $records['Amenities'] = $this->amenities();
        $records['Property'] = $this->properties($context, $uploader);
        $records['Buildings'] = $this->buildings($context, $records['Property']);
        $records['Hours'] = $this->hours($context, $records['Property']);
        $records['Floors'] = $this->floors($context, $records);
        $records['Spaces'] = $this->spaces($context, $records);
        $records['Seats'] = $this->seats($context, $records['Spaces']);
        $this->seatCounts($context, $records);
        $records['Occupancy'] = $this->occupancy($context, $records);
        $this->propertyWarnings($context, $records);
        $this->synthetic();

        foreach ($records as $sheet => $items) {
            $records[$sheet] = array_filter($items, fn (array $item): bool => ! isset($this->failed[$sheet][$item['_row']]));
        }

        usort($this->issues, fn (array $a, array $b): int => [array_search($a['sheet'], array_keys(self::COLUMNS), true), $a['row'] ?? 0] <=> [array_search($b['sheet'], array_keys(self::COLUMNS), true), $b['row'] ?? 0]);

        return ['issues' => $this->issues, 'records' => $records];
    }

    /**
     * V-001 to V-004: a readable workbook of a supported template, every data sheet with its columns,
     * within the size and row limits. Any failure stops validation.
     *
     * @param  array{sheets: array<string, array{headers: list<string>, rows: array<int, list<mixed>>}>, meta: array<string, string>}|null  $workbook
     */
    private function structure(?array $workbook, int $bytes): bool
    {
        if ($workbook === null || $bytes > self::MAX_BYTES) {
            $this->add('Workbook', null, null, 'V-004', 'error', $workbook === null ? 'This is not a readable Excel workbook (.xlsx). Download the SG-INV-1.3 template and fill it in.' : 'The file is larger than 10 MB.');

            return false;
        }

        if (preg_match('/^SG-INV-1\.\d+$/', $workbook['meta']['template_id'] ?? '') !== 1) {
            $this->add('_meta', null, 'template_id', 'V-001', 'error', 'This workbook is not an SG-INV-1.x template, or its hidden _meta sheet was changed. Start from the current template.', $workbook['meta']['template_id'] ?? null);

            return false;
        }

        $ok = true;

        foreach (self::COLUMNS as $sheet => $columns) {
            if (! isset($workbook['sheets'][$sheet])) {
                $this->add($sheet, null, null, 'V-002', 'error', 'The sheet "'.$sheet.'" is missing or was renamed.');
                $ok = false;

                continue;
            }

            $headers = $workbook['sheets'][$sheet]['headers'];

            foreach (array_diff($columns, $headers) as $missing) {
                $this->add($sheet, 1, $missing, 'V-003', 'error', 'The column "'.$missing.'" is missing from row 1.');
                $ok = false;
            }

            foreach (array_diff(array_filter($headers), $columns) as $extra) {
                $this->add($sheet, 1, $extra, 'V-003', 'warning', 'The column "'.$extra.'" is not part of the template and is ignored.');
            }

            $this->rows[$sheet] = collect($workbook['sheets'][$sheet]['rows'])
                ->map(fn (array $cells): array => collect($headers)->mapWithKeys(fn (string $key, int $index): array => [$key => $cells[$index] ?? null])->all())
                ->all();
        }

        if ($ok && collect($this->rows)->sum(fn (array $rows): int => count($rows)) > self::MAX_ROWS) {
            $this->add('Workbook', null, null, 'V-004', 'error', 'The workbook has more than 20,000 data rows.');
            $ok = false;
        }

        return $ok;
    }

    /**
     * Reference data and existing records the checks need, read once.
     *
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'cities' => City::query()->get()->flatMap(fn (City $city): array => collect([$city->name, ...($city->aliases ?? [])])->mapWithKeys(fn (string $name): array => [Str::lower($name) => $city])->all()),
            'products' => Product::query()->when(Product::hasSpaceTypes(), fn ($products) => $products->with('spaceTypes'))->get()->keyBy('key'),
            'types' => SpaceType::query()->get()->keyBy('key'),
            'properties' => Property::query()->with('city')->get()->keyBy('code'),
            'buildings' => Building::query()->with('property')->get()->keyBy('code'),
            'floors' => Floor::query()->with('building')->get()->keyBy(fn (Floor $floor): string => $floor->building->code.'|'.$floor->building_level),
            'spaces' => Space::withTrashed()->with(['property', 'floor.building'])->get()->keyBy('code'),
            'seats' => SpaceSeat::query()->with('space')->get()->keyBy(fn (SpaceSeat $seat): string => $seat->space->code.'|'.$seat->label),
            'allocations' => SpaceAllocation::query()->with(['space', 'seat'])->whereIn('status', AllocationStatus::occupying())->get(),
            'amenities' => Amenity::query()->pluck('name')->mapWithKeys(fn (string $name): array => [Str::lower($name) => $name]),
            'managers' => User::query()->where('is_active', true)->role(StaffRole::PropertyManager->value)->pluck('email')->map(fn (string $email): string => Str::lower($email))->all(),
            'today' => BusinessDay::today(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function amenities(): array
    {
        $records = [];

        foreach ($this->rows['Amenities'] as $row => $values) {
            if (! $this->required('Amenities', $row, $values)) {
                continue;
            }

            $this->length('Amenities', $row, $values, ['amenity_name']);
            $category = $this->listValue('Amenities', $row, 'category', $values['category'], 'amenity_category');
            $scope = $this->listValue('Amenities', $row, 'applies_to', $values['applies_to'], 'amenity_scope');
            $key = Str::lower((string) $values['amenity_name']);

            if (isset($records[$key])) {
                $this->add('Amenities', $row, 'amenity_name', 'V-120', 'error', 'This amenity is already listed on row '.$records[$key]['_row'].'.', $values['amenity_name']);

                continue;
            }

            $records[$key] = ['_row' => $row, 'name' => (string) $values['amenity_name'], 'category' => $category, 'applies_to' => $scope];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, array<string, mixed>>
     */
    private function properties(array $context, User $uploader): array
    {
        $records = [];
        $limited = ! $uploader->hasCapabilityForEveryProperty(Capability::ImportInventory);

        foreach ($this->rows['Property'] as $row => $values) {
            $code = $this->code('Property', $row, 'property_code', $values['property_code']);
            $this->required('Property', $row, $values);
            $this->length('Property', $row, $values, ['property_name', 'micro_market', 'city']);
            $model = $this->listValue('Property', $row, 'operating_model', $values['operating_model'], 'operating_model');
            $status = $this->listValue('Property', $row, 'operational_status', $values['operational_status'], 'property_status');
            $opensOn = $this->date('Property', $row, 'opens_on', $values['opens_on']);
            $city = filled($values['city']) ? ($context['cities'][Str::lower((string) $values['city'])] ?? null) : null;

            if (filled($values['city']) && $city === null) {
                $this->add('Property', $row, 'city', 'V-105', 'error', 'This city is not one SmartGro has recorded. Use its official current name (for example Gurugram); new cities are added in the back office first.', $values['city']);
            }

            if (in_array($status, ['planned', 'pre_opening'], true) && $opensOn === null && ! $this->hasError('Property', $row)) {
                $this->add('Property', $row, 'opens_on', 'V-101', 'error', 'A planned or pre-opening property needs its opening date.');
            }

            $zone = $values['timezone'] ?? null;

            if (filled($zone) && ! in_array($zone, DateTimeZone::listIdentifiers(), true)) {
                $this->add('Property', $row, 'timezone', 'V-108', 'error', 'This is not a valid time zone; leave it blank for Asia/Kolkata.', $zone);
            } elseif (filled($zone) && $zone !== 'Asia/Kolkata') {
                $this->add('Property', $row, 'timezone', 'V-108', 'error', 'Every SmartGro property is in India (OD-02) and uses Asia/Kolkata; leave it blank.', $zone);
            }

            $email = filled($values['property_manager_email']) ? Str::lower((string) $values['property_manager_email']) : null;

            if ($email !== null && (filter_var($email, FILTER_VALIDATE_EMAIL) === false || ! in_array($email, $context['managers'], true))) {
                $this->add('Property', $row, 'property_manager_email', 'V-111', 'warning', 'No active Property Manager has this email, so the property is imported without one.', $email);
            }

            if ($code === null) {
                continue;
            }

            if (isset($records[$code])) {
                $this->add('Property', $row, 'property_code', 'V-120', 'error', 'Property '.$code.' is already on row '.$records[$code]['_row'].'.', $code);

                continue;
            }

            $existing = $context['properties'][$code] ?? null;

            if ($limited && ($existing === null || ! $uploader->hasCapabilityForProperty(Capability::ImportInventory, $existing))) {
                $this->add('Property', $row, 'property_code', 'SCOPE', 'error', 'You can import only the properties assigned to you; operations staff add new properties.', $code);
            }

            if ($existing !== null && $city !== null && $existing->city_id !== $city->getKey()) {
                $this->add('Property', $row, 'city', 'V-121', 'error', 'Property '.$code.' already exists in '.$existing->city->name.'; a property never moves to another city.', $values['city']);
            } elseif ($existing !== null && $model !== null && $existing->operating_model->value !== $model) {
                $this->add('Property', $row, 'operating_model', 'V-122', 'warning', 'This changes the operating model of an existing property. Confirm it in the preview.', $values['operating_model']);
            }

            $records[$code] = [
                '_row' => $row, 'code' => $code, 'name' => $values['property_name'], 'city_id' => $city?->getKey(), 'micro_market' => $values['micro_market'],
                'operating_model' => $model, 'operational_status' => $status, 'opens_on' => $opensOn?->toDateString(), 'exists' => $existing !== null,
            ];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, array<string, mixed>>
     */
    private function buildings(array $context, array $properties): array
    {
        $records = [];
        $primaries = [];

        foreach ($this->rows['Buildings'] as $row => $values) {
            $property = $this->code('Buildings', $row, 'property_code', $values['property_code']);
            $code = $this->code('Buildings', $row, 'building_code', $values['building_code']);
            $this->required('Buildings', $row, $values);
            $this->length('Buildings', $row, $values, ['building_name', 'address_line_1', 'address_line_2', 'locality', 'state']);
            $primary = $this->yesNo('Buildings', $row, 'is_primary', $values['is_primary']);
            $country = filled($values['country_code']) ? Str::upper((string) $values['country_code']) : null;

            if ($country !== null && (preg_match('/^[A-Z]{2}$/', $country) !== 1 || in_array(Locale::getDisplayRegion('und_'.$country, 'en'), [$country, 'Unknown Region'], true))) {
                $this->add('Buildings', $row, 'country_code', 'V-108', 'error', 'Use a two-letter country code, IN for India.', $values['country_code']);
            }

            [$latitude, $longitude] = [$values['latitude'], $values['longitude']];

            if (($latitude === null) !== ($longitude === null)) {
                $this->add('Buildings', $row, $latitude === null ? 'latitude' : 'longitude', 'V-109', 'error', 'Fill in both latitude and longitude, or neither.');
            } elseif ($latitude !== null && (! is_numeric($latitude) || ! is_numeric($longitude) || abs((float) $latitude) > 90 || abs((float) $longitude) > 180)) {
                $this->add('Buildings', $row, 'latitude', 'V-109', 'error', 'Latitude is from -90 to 90 and longitude from -180 to 180, in decimal degrees.');
            }

            $postal = $values['postal_code'] === null ? null : (string) $values['postal_code'];

            if ($postal !== null && preg_match('/^[1-9][0-9]{5}$/', $postal) !== 1) {
                $this->add('Buildings', $row, 'postal_code', 'V-113', 'error', 'A PIN code has six digits and does not start with 0.', $postal);
            } elseif ($postal === null && ! $this->hasError('Buildings', $row)) {
                $this->add('Buildings', $row, 'postal_code', 'V-113', 'warning', 'Without a PIN code the property cannot be published (BR-PROP-3).');
            }

            if ($code === null || $property === null) {
                continue;
            }

            if (isset($records[$code])) {
                $this->add('Buildings', $row, 'building_code', 'V-120', 'error', 'Building '.$code.' is already on row '.$records[$code]['_row'].'.', $code);

                continue;
            }

            if (! $this->propertyKnown($property, $properties, $context)) {
                $this->add('Buildings', $row, 'property_code', 'V-131', 'error', 'Property '.$property.' is not on the Property sheet or in SmartGro.', $property);

                continue;
            }

            $existing = $context['buildings'][$code] ?? null;

            if ($existing !== null && $existing->property->code !== $property) {
                $this->add('Buildings', $row, 'building_code', 'V-121', 'error', 'Building '.$code.' belongs to property '.$existing->property->code.'; codes are never moved.', $code);

                continue;
            }

            if ($existing !== null && ($existing->address_line_1 !== $values['address_line_1'] || $existing->locality !== $values['locality'])) {
                $this->add('Buildings', $row, 'address_line_1', 'V-122', 'warning', 'This changes the address of an existing building. Confirm it in the preview.', $values['address_line_1']);
            }

            if ($primary === true && ! $this->hasError('Buildings', $row)) {
                if (isset($primaries[$property])) {
                    $this->add('Buildings', $row, 'is_primary', 'V-132', 'error', 'Property '.$property.' already has a primary building (row '.$primaries[$property].'); it has exactly one.');

                    continue;
                }

                $primaries[$property] = $row;
            }

            $records[$code] = [
                '_row' => $row, 'code' => $code, 'property' => $property, 'name' => $values['building_name'], 'address_line_1' => $values['address_line_1'],
                'address_line_2' => $values['address_line_2'], 'locality' => $values['locality'], 'postal_code' => $postal, 'state' => $values['state'],
                'country_code' => $country, 'latitude' => $latitude, 'longitude' => $longitude, 'is_primary' => $primary === true, 'exists' => $existing !== null,
            ];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, array<string, mixed>>
     */
    private function hours(array $context, array $properties): array
    {
        $records = [];

        foreach ($this->rows['Hours'] as $row => $values) {
            $property = $this->code('Hours', $row, 'property_code', $values['property_code']);
            $this->required('Hours', $row, $values);
            $day = $this->listValue('Hours', $row, 'day_of_week', $values['day_of_week'], 'weekday');
            $closed = $this->yesNo('Hours', $row, 'is_closed', $values['is_closed']);
            $opens = $this->time('Hours', $row, 'opens_at', $values['opens_at']);
            $closes = $this->time('Hours', $row, 'closes_at', $values['closes_at']);

            if ($closed === true && ($opens !== null || $closes !== null)) {
                $this->add('Hours', $row, 'opens_at', 'V-112', 'error', 'Leave the times blank on a day it is closed.');
            } elseif ($closed === false && ($opens === null || $closes === null)) {
                $this->add('Hours', $row, $opens === null ? 'opens_at' : 'closes_at', 'V-112', 'error', 'Give both opening and closing times on a day it is open.');
            } elseif ($closed === false && $closes <= $opens) {
                $this->add('Hours', $row, 'closes_at', 'V-112', 'error', 'The closing time must be after the opening time.', $closes);
            }

            if ($property === null || $day === null) {
                continue;
            }

            $key = $property.'|'.$day;

            if (isset($records[$key])) {
                $this->add('Hours', $row, 'day_of_week', 'V-120', 'error', $values['day_of_week'].' is already given for '.$property.' on row '.$records[$key]['_row'].'.', $values['day_of_week']);

                continue;
            }

            if (! $this->propertyKnown($property, $properties, $context)) {
                $this->add('Hours', $row, 'property_code', 'V-131', 'error', 'Property '.$property.' is not on the Property sheet or in SmartGro.', $property);

                continue;
            }

            $records[$key] = ['_row' => $row, 'property' => $property, 'day' => $day, 'opens' => $closed === true ? null : $opens, 'closes' => $closed === true ? null : $closes];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, array<string, mixed>>>  $records
     * @return array<string, array<string, mixed>>
     */
    private function floors(array $context, array $records): array
    {
        $floors = [];

        foreach ($this->rows['Floors'] as $row => $values) {
            $property = $this->code('Floors', $row, 'property_code', $values['property_code']);
            $building = $this->code('Floors', $row, 'building_code', $values['building_code']);
            $this->required('Floors', $row, $values);
            $this->length('Floors', $row, $values, ['floor_label', 'floor_plan_file']);
            $level = $this->integer('Floors', $row, 'building_level', $values['building_level'], -5, 200);
            $sort = $this->integer('Floors', $row, 'sort_order', $values['sort_order'], 0, 9999);

            if ($property === null || $building === null || $level === null) {
                continue;
            }

            $key = $building.'|'.$level;

            if (isset($floors[$key])) {
                $this->add('Floors', $row, 'building_level', 'V-120', 'error', 'Level '.$level.' of '.$building.' is already on row '.$floors[$key]['_row'].'.', (string) $level);

                continue;
            }

            if (! $this->buildingOf($building, $property, $records['Buildings'], $context, 'Floors', $row)) {
                continue;
            }

            $floors[$key] = ['_row' => $row, 'property' => $property, 'building' => $building, 'level' => $level, 'label' => $values['floor_label'], 'sort_order' => $sort, 'plan_file' => $values['floor_plan_file'], 'exists' => isset($context['floors'][$key])];
        }

        return $floors;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, array<string, mixed>>>  $records
     * @return array<string, array<string, mixed>>
     */
    private function spaces(array $context, array $records): array
    {
        $spaces = [];

        foreach ($this->rows['Spaces'] as $row => $values) {
            $property = $this->code('Spaces', $row, 'property_code', $values['property_code']);
            $building = $this->code('Spaces', $row, 'building_code', $values['building_code']);
            $code = $this->code('Spaces', $row, 'space_code', $values['space_code']);
            $this->required('Spaces', $row, $values);
            $this->length('Spaces', $row, $values, ['space_name', 'notes']);
            $type = $this->listValue('Spaces', $row, 'space_type', $values['space_type'], 'space_type');
            $mode = $this->listValue('Spaces', $row, 'inventory_mode', $values['inventory_mode'], 'inventory_mode');
            $capacity = $this->integer('Spaces', $row, 'capacity_seats', $values['capacity_seats'], 1, 2000);
            $area = $this->integer('Spaces', $row, 'area_sqft', $values['area_sqft'], 1, 1000000);
            $status = $this->listValue('Spaces', $row, 'operational_status', $values['operational_status'], 'space_status');
            $listable = $this->yesNo('Spaces', $row, 'publicly_listable', $values['publicly_listable']);
            $level = $this->integer('Spaces', $row, 'building_level', $values['building_level'], -5, 200);
            $minutes = collect(['turnaround_minutes', 'min_booking_minutes', 'max_booking_minutes'])->mapWithKeys(fn (string $column): array => [$column => $this->integer('Spaces', $row, $column, $values[$column], 0, 1440)])->all();
            $categories = collect(explode(';', (string) $values['sellable_as']))->map(fn (string $label): string => trim($label))->filter()->values();
            $categoryKeys = $categories->map(fn (string $label): ?string => self::LISTS['category'][Str::lower($label)] ?? ($context['products']->has($label) ? $label : null));

            foreach ($categories as $index => $label) {
                if ($categoryKeys[$index] === null) {
                    $this->add('Spaces', $row, 'sellable_as', 'V-105', 'error', '"'.$label.'" is not a workspace category. Use the names on the Read me sheet.', $label);
                }
            }

            if ($this->hasError('Spaces', $row) || $code === null || $property === null || $building === null) {
                $this->checkDuplicate($spaces, $code, 'Spaces', $row, 'space_code');

                continue;
            }

            if ($this->checkDuplicate($spaces, $code, 'Spaces', $row, 'space_code')) {
                continue;
            }

            if (! $this->propertyKnown($property, $records['Property'], $context)) {
                $this->add('Spaces', $row, 'property_code', 'V-131', 'error', 'Property '.$property.' is not on the Property sheet or in SmartGro.', $property);

                continue;
            }

            if (! $this->buildingOf($building, $property, $records['Buildings'], $context, 'Spaces', $row)) {
                continue;
            }

            if (! isset($records['Floors'][$building.'|'.$level]) && ! isset($context['floors'][$building.'|'.$level])) {
                $this->add('Spaces', $row, 'building_level', 'V-130', 'error', 'Level '.$level.' of '.$building.' is not on the Floors sheet or in SmartGro.', (string) $level);

                continue;
            }

            $existing = $context['spaces'][$code] ?? null;

            if ($existing !== null && $existing->property->code !== $property) {
                $this->add('Spaces', $row, 'space_code', 'V-121', 'error', 'Space '.$code.' belongs to property '.$existing->property->code.'; codes are never moved.', $code);

                continue;
            }

            $categoryKeys = $categoryKeys->filter()->unique()->values();

            if ($categoryKeys->intersect(self::TIME_BOOKED)->isNotEmpty()) {
                if ($minutes['min_booking_minutes'] === null || $minutes['max_booking_minutes'] === null) {
                    $this->add('Spaces', $row, $minutes['max_booking_minutes'] === null ? 'max_booking_minutes' : 'min_booking_minutes', 'V-115', 'error', 'A room booked by time needs its minimum and maximum booking length.');

                    continue;
                }

                if ($minutes['min_booking_minutes'] > $minutes['max_booking_minutes']) {
                    $this->add('Spaces', $row, 'min_booking_minutes', 'V-115', 'error', 'The minimum booking length is longer than the maximum.');

                    continue;
                }
            }

            $spaceType = $context['types'][$type] ?? null;

            if ($spaceType === null || ! $spaceType->allowsInventoryMode(InventoryMode::from($mode))) {
                $this->add('Spaces', $row, 'inventory_mode', 'V-116', 'error', 'A '.Str::lower($values['space_type']).' cannot be counted as "'.$values['inventory_mode'].'".', $values['inventory_mode']);

                continue;
            }

            $wrong = $categoryKeys->reject(fn (string $key): bool => $context['products']->has($key) && $context['products'][$key]->allowsInventoryMode(InventoryMode::from($mode)));

            if ($wrong->isNotEmpty()) {
                $this->add('Spaces', $row, 'sellable_as', 'V-117', 'error', 'A space counted as "'.$values['inventory_mode'].'" cannot be sold as '.$wrong->map(fn (string $key): string => $context['products'][$key]->name ?? $key)->implode(', ').' (BR-PRODCFG-2).', $values['sellable_as']);

                continue;
            }

            $otherType = $categoryKeys->reject(fn (string $key): bool => $context['products'][$key]->acceptsSpaceType($spaceType));

            if ($otherType->isNotEmpty()) {
                $this->add('Spaces', $row, 'sellable_as', 'V-117', 'error', 'A '.Str::lower($values['space_type']).' cannot be sold as '.$otherType->map(fn (string $key): string => $context['products'][$key]->name)->implode(', ').': that category does not accept this space type (BR-PRODCFG-2).', $values['sellable_as']);

                continue;
            }

            $amenities = collect(explode(';', (string) $values['amenities']))->map(fn (string $name): string => trim($name))->filter()->values();
            $known = [];

            foreach ($amenities as $name) {
                $match = $records['Amenities'][Str::lower($name)]['name'] ?? $context['amenities'][Str::lower($name)] ?? null;

                if ($match === null) {
                    $suggestion = collect([...array_column($records['Amenities'], 'name'), ...$context['amenities']->values()])->sortBy(fn (string $candidate): int => levenshtein(Str::lower($candidate), Str::lower($name)))->first();
                    $this->add('Spaces', $row, 'amenities', 'W-160', 'warning', '"'.$name.'" is not on the Amenities sheet'.($suggestion !== null ? ' (did you mean "'.$suggestion.'"?)' : '').'; it is not imported.', $name);
                } else {
                    $known[] = $match;
                }
            }

            if ($area !== null && ($area / $capacity < 20 || $area / $capacity > 500)) {
                $this->add('Spaces', $row, 'area_sqft', 'W-162', 'warning', 'That is '.round($area / $capacity).' sq ft per seat, which looks like a typing error.', (string) $area);
            }

            $this->personalData('Spaces', $row, 'notes', $values['notes']);

            $spaces[$code] = [
                '_row' => $row, 'code' => $code, 'property' => $property, 'building' => $building, 'level' => $level, 'name' => $values['space_name'],
                'type' => $type, 'mode' => $mode, 'capacity' => $capacity, 'area' => $area, 'status' => $status, 'listable' => $listable === true,
                'categories' => $categoryKeys->all(), 'amenities' => $known, 'turnaround' => $minutes['turnaround_minutes'] ?? ($categoryKeys->intersect(self::TIME_BOOKED)->isNotEmpty() ? 0 : null),
                'min_booking' => $minutes['min_booking_minutes'], 'max_booking' => $minutes['max_booking_minutes'], 'exists' => $existing !== null,
            ];
        }

        return $spaces;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, mixed>>  $spaces
     * @return array<string, array<string, mixed>>
     */
    private function seats(array $context, array $spaces): array
    {
        $seats = [];

        foreach ($this->rows['Seats'] as $row => $values) {
            $space = $this->code('Seats', $row, 'space_code', $values['space_code']);
            $this->required('Seats', $row, $values);
            $this->length('Seats', $row, $values, ['seat_label']);
            $active = $this->yesNo('Seats', $row, 'is_active', $values['is_active']);

            if ($space === null || $values['seat_label'] === null || $this->hasError('Seats', $row)) {
                continue;
            }

            $key = $space.'|'.$values['seat_label'];

            if (isset($seats[$key])) {
                $this->add('Seats', $row, 'seat_label', 'V-120', 'error', 'Seat '.$values['seat_label'].' of '.$space.' is already on row '.$seats[$key]['_row'].'.', (string) $values['seat_label']);

                continue;
            }

            $mode = $spaces[$space]['mode'] ?? ($context['spaces'][$space]?->inventory_mode->value ?? null);

            if ($mode === null) {
                if (! $this->onSheetWithErrors('Spaces', 'space_code', $space)) {
                    $this->add('Seats', $row, 'space_code', 'V-135', 'error', 'Space '.$space.' is not on the Spaces sheet or in SmartGro.', $space);
                }

                continue;
            }

            if (! InventoryMode::from($mode)->hasSeats()) {
                $this->add('Seats', $row, 'space_code', 'V-135', 'error', 'Seats are listed only for spaces counted per seat or as a hybrid zone.', $space);

                continue;
            }

            $seats[$key] = ['_row' => $row, 'space' => $space, 'label' => (string) $values['seat_label'], 'active' => $active === true];
        }

        return $seats;
    }

    /**
     * V-135: a per-seat or hybrid space has as many active seats as its capacity (file and database).
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, array<string, mixed>>>  $records
     */
    private function seatCounts(array $context, array $records): void
    {
        foreach ($records['Spaces'] as $code => $space) {
            if (! InventoryMode::from($space['mode'])->hasSeats()) {
                continue;
            }

            $active = collect($context['seats'])->filter(fn (SpaceSeat $seat): bool => $seat->space->code === $code)
                ->mapWithKeys(fn (SpaceSeat $seat): array => [$seat->label => $seat->is_active])
                ->merge(collect($records['Seats'])->where('space', $code)->mapWithKeys(fn (array $seat): array => [$seat['label'] => $seat['active']]))
                ->filter()->count();

            if ($active !== $space['capacity']) {
                $this->add('Spaces', $space['_row'], 'capacity_seats', 'V-135', 'error', 'This zone has '.$active.' active '.Str::plural('seat', $active).' on the Seats sheet and in SmartGro, but a capacity of '.$space['capacity'].'. They must be equal.', (string) $space['capacity']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, array<string, mixed>>>  $records
     * @return array<string, array<string, mixed>>
     */
    private function occupancy(array $context, array $records): array
    {
        $agreements = [];
        $today = $context['today'];

        foreach ($this->rows['Occupancy'] as $row => $values) {
            $space = $this->code('Occupancy', $row, 'space_code', $values['space_code']);
            $this->required('Occupancy', $row, $values);
            $this->length('Occupancy', $row, $values, ['contract_reference', 'seat_label']);
            $seats = $this->integer('Occupancy', $row, 'seats_allocated', $values['seats_allocated'], 1, 2000);
            $starts = $this->date('Occupancy', $row, 'starts_on', $values['starts_on']);
            $ends = $this->date('Occupancy', $row, 'ends_on', $values['ends_on']);
            $notice = $this->date('Occupancy', $row, 'notice_given_on', $values['notice_given_on']);
            $status = $this->listValue('Occupancy', $row, 'status', $values['status'], 'allocation_status');
            $this->personalData('Occupancy', $row, 'contract_reference', $values['contract_reference']);

            if ($starts !== null && $ends !== null && $ends->lessThan($starts)) {
                $this->add('Occupancy', $row, 'ends_on', 'V-140', 'error', 'The end date is before the start date.', $ends->toDateString());
            } elseif ($notice !== null && $ends !== null && $notice->greaterThan($ends)) {
                $this->add('Occupancy', $row, 'notice_given_on', 'V-140', 'error', 'Notice is dated after the end date.', $notice->toDateString());
            } elseif ($status === 'notice_period' && ($notice === null || $ends === null)) {
                $this->add('Occupancy', $row, $notice === null ? 'notice_given_on' : 'ends_on', 'V-140', 'error', 'A notice period needs both the notice date and the end date.');
            } elseif ($status === 'ended' && ($ends === null || ! $ends->lessThan($today))) {
                $this->add('Occupancy', $row, 'ends_on', 'V-140', 'error', 'An ended agreement needs an end date in the past.');
            } elseif ($status === 'active' && $starts !== null && $starts->greaterThan($today)) {
                $this->add('Occupancy', $row, 'starts_on', 'V-140', 'error', 'An active agreement must have started; use Reserved for one starting later.', $starts->toDateString());
            }

            if ($space === null || $this->hasError('Occupancy', $row)) {
                continue;
            }

            $target = $records['Spaces'][$space] ?? null;
            $existing = $context['spaces'][$space] ?? null;

            if ($target === null && $existing === null) {
                if (! $this->onSheetWithErrors('Spaces', 'space_code', $space)) {
                    $this->add('Occupancy', $row, 'space_code', 'V-145', 'error', 'Space '.$space.' is not on the Spaces sheet or in SmartGro.', $space);
                }

                continue;
            }

            $mode = $target['mode'] ?? $existing->inventory_mode->value;
            $label = $values['seat_label'] === null ? null : (string) $values['seat_label'];

            if ($label !== null && ! InventoryMode::from($mode)->hasSeats()) {
                $this->add('Occupancy', $row, 'seat_label', 'V-145', 'error', 'A seat is given only for spaces counted per seat or as a hybrid zone.', $label);

                continue;
            }

            if ($label === null && $mode === InventoryMode::PerSeat->value) {
                $this->add('Occupancy', $row, 'seat_label', 'V-145', 'error', 'Give the seat: in this zone each seat is allocated on its own.');

                continue;
            }

            if ($label !== null && ! isset($records['Seats'][$space.'|'.$label]) && ! isset($context['seats'][$space.'|'.$label])) {
                $this->add('Occupancy', $row, 'seat_label', 'V-145', 'error', 'Seat '.$label.' of '.$space.' is not on the Seats sheet or in SmartGro.', $label);

                continue;
            }

            $categories = $target['categories'] ?? $existing->offerings()->with('product')->get()->pluck('product.key')->all();

            if (array_intersect($categories, self::TERM_AGREEMENT) === []) {
                $this->add('Occupancy', $row, 'space_code', 'V-151', 'error', 'This space is booked by time, not allocated: it is not sold under a term-agreement category.', $space);

                continue;
            }

            $key = $values['contract_reference'].'|'.$space.'|'.$label.'|'.$starts->toDateString();

            if (isset($agreements[$key])) {
                $this->add('Occupancy', $row, 'starts_on', 'V-120', 'error', 'This agreement is already on row '.$agreements[$key]['_row'].'.', $starts->toDateString());

                continue;
            }

            $spaceStatus = $target['status'] ?? $existing->operational_status->value;

            if ($spaceStatus !== SpaceStatus::Active->value && $status !== 'ended') {
                $this->add('Occupancy', $row, 'space_code', 'V-152', 'warning', 'This agreement is on a space that is '.Str::lower(SpaceStatus::from($spaceStatus)->label()).'.', $space);
            }

            $agreements[$key] = [
                '_row' => $row, 'space' => $space, 'seat' => $label, 'reference' => (string) $values['contract_reference'], 'seats' => $mode === InventoryMode::Exclusive->value ? ($target['capacity'] ?? $existing->capacity_seats) : ($label !== null ? 1 : $seats),
                'starts_on' => $starts->toDateString(), 'ends_on' => $ends?->toDateString(), 'notice_given_on' => $notice?->toDateString(), 'status' => $status,
            ];
        }

        $this->overAllocation($context, $records, $agreements);

        return $agreements;
    }

    /**
     * V-150: on every day, the agreements in force on a space fit it: a whole space holds one agreement,
     * a seat holds one, and a pool or hybrid zone holds no more seats than it has (BR-AV-2, BR-AV-3,
     * BR-AV-3a, BR-AV-3d). Reported on the agreement, in file order, that first goes over.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, array<string, mixed>>>  $records
     * @param  array<string, array<string, mixed>>  $agreements
     */
    private function overAllocation(array $context, array $records, array &$agreements): void
    {
        $accepted = collect($context['allocations'])->map(fn (SpaceAllocation $allocation): array => [
            'space' => $allocation->space->code, 'seat' => $allocation->seat?->label, 'seats' => $allocation->seats_allocated,
            'starts_on' => $allocation->starts_on->toDateString(), 'ends_on' => $allocation->ends_on?->toDateString(), 'reference' => $allocation->agreement_reference,
        ])->values()->all();

        foreach ($agreements as $key => $agreement) {
            if ($agreement['status'] === 'ended') {
                continue;
            }

            $space = $records['Spaces'][$agreement['space']] ?? null;
            $existing = $context['spaces'][$agreement['space']] ?? null;
            $mode = InventoryMode::from($space['mode'] ?? $existing->inventory_mode->value);
            $capacity = $space['capacity'] ?? $existing->capacity_seats;
            $same = collect($accepted)->where('space', $agreement['space'])->reject(fn (array $other): bool => $other['reference'] === $agreement['reference'] && $other['seat'] === $agreement['seat'] && $other['starts_on'] === $agreement['starts_on']);
            $overlapping = $same->filter(fn (array $other): bool => $other['starts_on'] <= ($agreement['ends_on'] ?? '9999-12-31') && ($other['ends_on'] ?? '9999-12-31') >= $agreement['starts_on']);
            $days = $overlapping->pluck('starts_on')->filter(fn (string $day): bool => $day > $agreement['starts_on'] && $day <= ($agreement['ends_on'] ?? '9999-12-31'))->push($agreement['starts_on'])->unique();
            $peak = $days->map(fn (string $day): int => (int) $overlapping->filter(fn (array $other): bool => $other['starts_on'] <= $day && ($other['ends_on'] ?? '9999-12-31') >= $day)->sum('seats'))->max();

            $over = match (true) {
                $mode === InventoryMode::Exclusive => $overlapping->isNotEmpty() ? 'The space already has agreement '.$overlapping->first()['reference'].' on some of these dates; a whole space holds one agreement at a time.' : null,
                $agreement['seat'] !== null && $overlapping->where('seat', $agreement['seat'])->isNotEmpty() => 'Seat '.$agreement['seat'].' already has agreement '.$overlapping->where('seat', $agreement['seat'])->first()['reference'].' on some of these dates.',
                $peak + $agreement['seats'] > $capacity => 'With this agreement '.($peak + $agreement['seats']).' seats are taken on some day, but the zone has '.$capacity.' (BR-AV-3, BR-AV-3d).',
                default => null,
            };

            if ($over !== null) {
                $this->add('Occupancy', $agreement['_row'], 'seats_allocated', 'V-150', 'error', $over, (string) $agreement['seats']);

                continue;
            }

            $accepted[] = $agreement;
        }
    }

    /**
     * V-114, V-132 and W-161, on the properties that passed their own checks.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, array<string, array<string, mixed>>>  $records
     */
    private function propertyWarnings(array $context, array $records): void
    {
        foreach ($records['Property'] as $code => $property) {
            if ($this->hasError('Property', $property['_row'])) {
                continue;
            }

            $buildings = collect($records['Buildings'])->where('property', $code);
            $existing = $context['buildings']->filter(fn (Building $building): bool => $building->property->code === $code);

            if ($buildings->isEmpty() && $existing->isEmpty()) {
                $this->add('Property', $property['_row'], 'property_code', 'V-132', 'error', 'Property '.$code.' has no building on the Buildings sheet or in SmartGro.', $code);
            } elseif ($buildings->where('is_primary', true)->isEmpty() && $existing->where('is_primary', true)->isEmpty()) {
                $this->add('Property', $property['_row'], 'property_code', 'V-132', 'error', 'Property '.$code.' has no primary building; mark exactly one.', $code);
            } elseif ($buildings->where('is_primary', true)->isNotEmpty() && $existing->where('is_primary', true)->reject(fn (Building $building): bool => $buildings->has($building->code))->isNotEmpty()) {
                $this->add('Buildings', $buildings->firstWhere('is_primary', true)['_row'], 'is_primary', 'V-132', 'error', 'Property '.$code.' already has a primary building in SmartGro.');
            }

            $days = collect($records['Hours'])->where('property', $code)->count();

            if ($days !== 7) {
                $this->add('Property', $property['_row'], 'property_code', 'V-114', 'warning', 'Property '.$code.' has '.$days.' of 7 days on the Hours sheet; missing days are treated as closed.', $code);
            }

            if (in_array($property['operational_status'], ['planned', 'pre_opening'], true)
                && collect($records['Spaces'])->where('property', $code)->contains(fn (array $space): bool => $space['status'] === 'active' && $space['listable'])) {
                $this->add('Property', $property['_row'], 'operational_status', 'W-161', 'warning', 'Property '.$code.' is not open yet, so its spaces marked for customers will not be bookable (BR-AV-6).', $code);
            }
        }
    }

    /**
     * V-180: synthetic test data ("SYN-" codes, "SYNTHETIC" names) is refused outside local and testing.
     */
    private function synthetic(): void
    {
        if (app()->environment(['local', 'testing'])) {
            return;
        }

        foreach ($this->rows as $sheet => $rows) {
            foreach ($rows as $row => $values) {
                $column = collect($values)->search(fn (mixed $value, string $column): bool => is_string($value) && (str_starts_with(Str::upper($value), 'SYN-') || Str::contains($value, 'SYNTHETIC', true)));

                if ($column !== false) {
                    $this->add($sheet, $row, $column, 'V-180', 'error', 'This is synthetic test data, which is never imported here.', $values[$column]);
                }
            }
        }
    }

    /**
     * V-102: a permanent code, upper-cased with a note when typed in lower case.
     */
    private function code(string $sheet, int $row, string $column, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        $upper = Str::upper($text);

        if (preg_match(self::CODE_PATTERN, $upper) !== 1) {
            $this->add($sheet, $row, $column, 'V-102', 'error', 'Codes are 2 to 40 capital letters, digits and inner hyphens, without spaces or symbols.', $text);

            return null;
        }

        if ($upper !== $text) {
            $this->add($sheet, $row, $column, 'V-102', 'note', 'Changed to capitals: '.$upper.'.', $text);
        }

        return $upper;
    }

    /**
     * V-101 for each required column. True when all are present.
     *
     * @param  array<string, mixed>  $values
     */
    private function required(string $sheet, int $row, array $values): bool
    {
        $missing = collect(self::REQUIRED[$sheet])->filter(fn (string $column): bool => $values[$column] === null);

        foreach ($missing as $column) {
            $this->add($sheet, $row, $column, 'V-101', 'error', 'This value is required.');
        }

        return $missing->isEmpty();
    }

    /**
     * V-103.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $columns
     */
    private function length(string $sheet, int $row, array $values, array $columns): void
    {
        foreach ($columns as $column) {
            if ($values[$column] !== null && mb_strlen((string) $values[$column]) > self::LENGTHS[$column]) {
                $this->add($sheet, $row, $column, 'V-103', 'error', 'At most '.self::LENGTHS[$column].' characters.', (string) $values[$column]);
            }
        }
    }

    /**
     * V-105: a dropdown label or its stored key, case-insensitively.
     */
    private function listValue(string $sheet, int $row, string $column, mixed $value, string $list): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = Str::lower(trim((string) $value));
        $key = self::LISTS[$list][$text] ?? (in_array($text, self::LISTS[$list], true) ? $text : null);

        if ($key === null) {
            $this->add($sheet, $row, $column, 'V-105', 'error', '"'.$value.'" is not one of the allowed values. Choose from the list.', (string) $value);
        }

        return $key;
    }

    /**
     * V-106.
     */
    private function yesNo(string $sheet, int $row, string $column, mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return match (Str::lower(trim((string) $value))) {
            'yes' => true,
            'no' => false,
            default => $this->add($sheet, $row, $column, 'V-106', 'error', 'Use Yes or No.', (string) $value) ?? null,
        };
    }

    /**
     * V-104: a real date between 2000 and 2099, as an Excel date or YYYY-MM-DD.
     */
    private function date(string $sheet, int $row, string $column, mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::parse($value->format('Y-m-d'))
            : (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1 && checkdate((int) substr((string) $value, 5, 2), (int) substr((string) $value, 8, 2), (int) substr((string) $value, 0, 4)) ? CarbonImmutable::parse((string) $value) : null);

        if ($date === null || $date->year < 2000 || $date->year > 2099) {
            $this->add($sheet, $row, $column, 'V-104', 'error', 'Use a real date from 2000 to 2099, such as 2027-03-01.', (string) ($value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value));

            return null;
        }

        return $date;
    }

    /**
     * V-107: a 24-hour HH:MM time.
     */
    private function time(string $sheet, int $row, string $column, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = $value instanceof DateTimeInterface ? $value->format('H:i') : trim((string) $value);

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $text) !== 1) {
            $this->add($sheet, $row, $column, 'V-107', 'error', 'Use a 24-hour time such as 09:00.', $text);

            return null;
        }

        return $text;
    }

    /**
     * V-110: a whole number within its range.
     */
    private function integer(string $sheet, int $row, string $column, mixed $value, int $min, int $max): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value) || (float) $value !== floor((float) $value) || (int) $value < $min || (int) $value > $max) {
            $this->add($sheet, $row, $column, 'V-110', 'error', 'Use a whole number from '.number_format($min).' to '.number_format($max).'.', (string) $value);

            return null;
        }

        return (int) $value;
    }

    /**
     * W-170: an email address or phone number in free text.
     */
    private function personalData(string $sheet, int $row, string $column, mixed $value): void
    {
        if (is_string($value) && preg_match('/[^\s@]+@[^\s@]+\.[a-z]{2,}|(\+?\d[\d\s-]{8,}\d)/i', $value) === 1) {
            $this->add($sheet, $row, $column, 'W-170', 'warning', 'This looks like personal data (an email address or phone number). Remove it: use internal references only.', $value);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  array<string, mixed>  $context
     */
    private function propertyKnown(string $code, array $properties, array $context): bool
    {
        return isset($properties[$code]) || isset($context['properties'][$code]) || $this->onSheetWithErrors('Property', 'property_code', $code);
    }

    /**
     * V-133: the building exists (sheet or database) and belongs to the same property.
     *
     * @param  array<string, array<string, mixed>>  $buildings
     * @param  array<string, mixed>  $context
     */
    private function buildingOf(string $building, string $property, array $buildings, array $context, string $sheet, int $row): bool
    {
        $owner = $buildings[$building]['property'] ?? $context['buildings'][$building]?->property->code ?? null;

        if ($owner === null) {
            if (! $this->onSheetWithErrors('Buildings', 'building_code', $building)) {
                $this->add($sheet, $row, 'building_code', 'V-133', 'error', 'Building '.$building.' is not on the Buildings sheet or in SmartGro.', $building);
            }

            return false;
        }

        if ($owner !== $property) {
            $this->add($sheet, $row, 'building_code', 'V-133', 'error', 'Building '.$building.' belongs to property '.$owner.', not '.$property.'.', $building);

            return false;
        }

        return true;
    }

    /**
     * V-120 for a code; true when the code is a repeat.
     *
     * @param  array<string, array<string, mixed>>  $records
     */
    private function checkDuplicate(array $records, ?string $code, string $sheet, int $row, string $column): bool
    {
        $earlier = $code === null ? null : collect($this->rows[$sheet])->filter(fn (array $values, int $other): bool => $other < $row && Str::upper(trim((string) $values[$column])) === $code)->keys()->first();

        if ($earlier !== null) {
            $this->add($sheet, $row, $column, 'V-120', 'error', $code.' is already on row '.$earlier.'.', $code);

            return true;
        }

        return false;
    }

    /**
     * Whether a key appears on a sheet only in rows that failed, so no second message is given about it.
     */
    private function onSheetWithErrors(string $sheet, string $column, string $code): bool
    {
        return collect($this->rows[$sheet])->contains(fn (array $values, int $row): bool => Str::upper(trim((string) $values[$column])) === $code && isset($this->failed[$sheet][$row]));
    }

    private function hasError(string $sheet, int $row): bool
    {
        return isset($this->failed[$sheet][$row]);
    }

    private function add(string $sheet, ?int $row, ?string $column, string $rule, string $severity, string $message, ?string $value = null): null
    {
        if ($severity === 'error' && $row !== null) {
            $this->failed[$sheet][$row] = true;
        }

        $this->issues[] = ['sheet' => $sheet, 'row' => $row, 'column' => $column, 'rule' => $rule, 'severity' => $severity, 'message' => $message, 'value' => $value];

        return null;
    }
}
