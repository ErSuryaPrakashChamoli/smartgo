<?php

namespace App\Actions\Properties;

use App\Enums\Access\Capability;
use App\Enums\Properties\OperatingModel;
use App\Enums\Properties\PropertyStatus;
use App\Models\Building;
use App\Models\Offering;
use App\Models\Property;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Adds or changes a property with its primary building (the address), its opening hours and the
 * workspace categories it offers, in one transaction (OD-08, SECURITY_MODEL §3.1). Only facts staff
 * enter are stored: nothing is defaulted about capacity, prices, hours or availability. Property fields
 * are audited by the model; changes to the categories offered are audited here. A new property gets an
 * empty website profile, which stays unpublished until a version is approved and published.
 *
 * The categories offered change only when `product_ids` is given, and only for users who may manage
 * workspace categories. A category no longer offered is deactivated, never deleted, so its profile and
 * enquiries keep their reference; a newly offered category gets an empty, unpublished profile.
 *
 * A Property Manager edits only the address details and opening hours of an assigned property. The
 * property's identity, address on the website, city, sector, operating model, operational status and
 * opening date stay with operations staff (Phase 5 directive): they are never written for a Property
 * Manager, and sending a different value is refused.
 */
class SaveProperty
{
    public const string CODE_PATTERN = '/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/';

    public const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Property fields only users who manage every property may change.
     *
     * @var list<string>
     */
    public const array OPERATIONS_ONLY_FIELDS = ['name', 'code', 'slug', 'city_id', 'micro_market_id', 'operating_model', 'operational_status', 'opens_on'];

    /**
     * Address segments reserved by other location routes.
     *
     * @var list<string>
     */
    public const array RESERVED_SLUGS = ['area'];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, ?Property $property, array $data): Property
    {
        Gate::forUser($user)->authorize($property === null ? 'create' : 'update', $property ?? Property::class);

        if (array_key_exists('product_ids', $data) && ! $user->can(Capability::ManageProducts)) {
            throw new AuthorizationException('You cannot change which workspace categories a property offers.');
        }

        $isLimited = $property !== null && ! $user->hasCapabilityForEveryProperty(Capability::ManageProperties);
        $sentOperationsFields = $isLimited ? Arr::only($data, self::OPERATIONS_ONLY_FIELDS) : [];

        if ($isLimited) {
            $data = [...$this->operationsFields($property), ...$data, 'building' => [...['code' => $property->primaryBuilding?->code], ...($data['building'] ?? [])]];
        }

        $data = $this->withShortTimes($data);
        $validated = Validator::make($data, $this->rules($property, $data), $this->messages(), $this->attributes())->validate();

        return DB::transaction(function () use ($user, $property, $validated, $isLimited, $sentOperationsFields): Property {
            $property = $property === null ? new Property : Property::query()->lockForUpdate()->findOrFail($property->getKey());

            if ($isLimited) {
                $this->refuseOperationsChanges($property, $sentOperationsFields);
            }

            $property->forceFill([
                ...($isLimited ? [] : Arr::only($validated, self::OPERATIONS_ONLY_FIELDS)),
                'operating_hours' => $this->operatingHours($validated['operating_hours'] ?? []),
            ])->save();
            $property->profile()->firstOrCreate([]);

            $building = $property->primaryBuilding()->first() ?? new Building;
            $building->forceFill([
                ...Arr::only($validated['building'], ['name', 'address_line_1', 'address_line_2', 'locality', 'postal_code', 'state', 'latitude', 'longitude']),
                'code' => ($isLimited ? null : ($validated['building']['code'] ?? null)) ?? $building->code ?? $property->code.'-MAIN',
                'property_id' => $property->getKey(),
                'city_id' => $property->city_id,
                'is_primary' => true,
            ])->save();

            if (array_key_exists('product_ids', $validated)) {
                $this->syncOfferings($user, $property, array_map('intval', $validated['product_ids'] ?? []));
            }

            return $property->fresh(['primaryBuilding', 'products']);
        });
    }

    /**
     * The property's current operations-only values, in the form SaveProperty takes them.
     *
     * @return array<string, mixed>
     */
    private function operationsFields(Property $property): array
    {
        return [
            ...$property->only(['name', 'code', 'slug', 'city_id', 'micro_market_id']),
            'operating_model' => $property->operating_model->value,
            'operational_status' => $property->operational_status->value,
            'opens_on' => $property->opens_on?->toDateString(),
        ];
    }

    /**
     * Refuses a change a Property Manager sent to a field only operations staff may change, compared
     * with the locked property, so a value read before someone else's change is never written back.
     *
     * @param  array<string, mixed>  $sent
     *
     * @throws AuthorizationException
     */
    private function refuseOperationsChanges(Property $property, array $sent): void
    {
        $current = $this->operationsFields($property);
        $changed = array_keys(array_filter($sent, fn (mixed $value, string $field): bool => (string) $value !== (string) ($current[$field] ?? ''), ARRAY_FILTER_USE_BOTH));

        if ($changed !== []) {
            throw new AuthorizationException('Only operations staff can change a property\'s name, code, website address, city, sector, operating model, status or opening date.');
        }
    }

    /**
     * Opening hours as HH:MM, so a time sent with seconds (09:00:00) is checked like any other.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withShortTimes(array $data): array
    {
        foreach (is_array($data['operating_hours'] ?? null) ? $data['operating_hours'] : [] as $day => $hours) {
            foreach (['opens', 'closes'] as $field) {
                if (is_array($hours) && is_string($hours[$field] ?? null) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $hours[$field])) {
                    $data['operating_hours'][$day][$field] = substr($hours[$field], 0, 5);
                }
            }
        }

        return $data;
    }

    /**
     * Each weekday with both times, or null; null overall until any day is recorded.
     *
     * @param  array<string, mixed>  $hours
     * @return array<string, array{opens: string, closes: string}|null>|null
     */
    private function operatingHours(array $hours): ?array
    {
        $days = collect(Property::WEEKDAYS)->mapWithKeys(fn (string $label, string $day): array => [
            $day => filled($hours[$day]['opens'] ?? null) ? ['opens' => (string) $hours[$day]['opens'], 'closes' => (string) $hours[$day]['closes']] : null,
        ]);

        return $days->filter()->isEmpty() ? null : $days->all();
    }

    /**
     * Records which categories the property offers: new ones are added with an empty profile, ones no
     * longer offered are deactivated (their profile and enquiries keep the reference), and ones offered
     * again are reactivated. The property row is already locked.
     *
     * @param  list<int>  $productIds
     */
    private function syncOfferings(User $user, Property $property, array $productIds): void
    {
        $offerings = Offering::query()->where('property_id', $property->getKey())->lockForUpdate()->get()->keyBy('product_id');
        $added = [];
        $removed = [];

        foreach (array_unique($productIds) as $productId) {
            $offering = $offerings->get($productId) ?? (new Offering)->forceFill(['property_id' => $property->getKey(), 'product_id' => $productId, 'is_active' => false]);

            if (! $offering->is_active) {
                $offering->forceFill(['is_active' => true])->save();
                $added[] = $productId;
            }

            $offering->profile()->firstOrCreate([]);
        }

        foreach ($offerings as $productId => $offering) {
            if ($offering->is_active && ! in_array($productId, $productIds, true)) {
                $offering->forceFill(['is_active' => false])->save();
                $removed[] = $productId;
            }
        }

        if ($added === [] && $removed === []) {
            return;
        }

        activity('properties')
            ->performedOn($property)
            ->causedBy($user)
            ->event('offerings_changed')
            ->withProperties(['added' => $added, 'removed' => $removed])
            ->log('Workspace categories offered changed');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function rules(?Property $property, array $data): array
    {
        $plainText = fn (bool $required, int $max): array => [$required ? 'required' : 'nullable', 'string', 'max:'.$max, 'not_regex:/[<>]/'];
        $buildingId = $property?->primaryBuilding()->value('id');

        return [
            'name' => $plainText(true, 80),
            'code' => ['required', 'string', 'max:20', 'regex:'.self::CODE_PATTERN, Rule::unique('properties', 'code')->ignore($property?->getKey())],
            'slug' => ['required', 'string', 'max:80', 'regex:'.self::SLUG_PATTERN, Rule::notIn(self::RESERVED_SLUGS),
                Rule::unique('properties', 'slug')->where('city_id', $data['city_id'] ?? null)->ignore($property?->getKey())],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
            'micro_market_id' => ['nullable', 'integer', Rule::exists('micro_markets', 'id')->where('city_id', $data['city_id'] ?? null)],
            'operating_model' => ['required', Rule::enum(OperatingModel::class)],
            'operational_status' => ['required', Rule::enum(PropertyStatus::class)],
            'opens_on' => ['nullable', 'date'],
            'building' => ['required', 'array'],
            'building.code' => ['nullable', 'string', 'max:30', 'regex:'.self::CODE_PATTERN, Rule::unique('buildings', 'code')->ignore($buildingId)],
            'building.name' => $plainText(false, 80),
            'building.address_line_1' => $plainText(true, 120),
            'building.address_line_2' => $plainText(false, 120),
            'building.locality' => $plainText(true, 80),
            'building.postal_code' => ['nullable', 'string', 'regex:/^[1-9][0-9]{5}$/'],
            'building.state' => $plainText(false, 60),
            'building.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'building.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'operating_hours' => ['nullable', 'array:'.implode(',', array_keys(Property::WEEKDAYS))],
            ...collect(Property::WEEKDAYS)->keys()->flatMap(fn (string $day): array => [
                "operating_hours.{$day}" => ['nullable', 'array:opens,closes'],
                "operating_hours.{$day}.opens" => ['nullable', 'date_format:H:i', "required_with:operating_hours.{$day}.closes"],
                "operating_hours.{$day}.closes" => ['nullable', 'date_format:H:i', "required_with:operating_hours.{$day}.opens", "after:operating_hours.{$day}.opens"],
            ])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            '*.not_regex' => 'Use plain text: < and > are not allowed.',
            'building.*.not_regex' => 'Use plain text: < and > are not allowed.',
            'code.regex' => 'Use capital letters, numbers and hyphens, for example A-70.',
            'building.code.regex' => 'Use capital letters, numbers and hyphens, for example A-70-MAIN.',
            'slug.regex' => 'Use lowercase letters, numbers and hyphens, for example a-70.',
            'slug.unique' => 'Another property in this city already uses this address.',
            'slug.not_in' => 'This address is reserved for other location pages. Choose another.',
            'micro_market_id.exists' => 'Choose a sector in the same city.',
            'building.postal_code.regex' => 'A PIN code has six digits and does not start with 0.',
            'operating_hours.*.opens.required_with' => 'Enter the opening time too, or leave both empty.',
            'operating_hours.*.closes.required_with' => 'Enter the closing time too, or leave both empty.',
            'operating_hours.*.closes.after' => 'The closing time must be after the opening time.',
            'operating_hours.*.*.date_format' => 'Use a time such as 09:00.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'city_id' => 'city',
            'micro_market_id' => 'sector',
            'building.address_line_1' => 'address line 1',
            'building.locality' => 'sector or locality',
            'building.postal_code' => 'PIN code',
        ];
    }
}
