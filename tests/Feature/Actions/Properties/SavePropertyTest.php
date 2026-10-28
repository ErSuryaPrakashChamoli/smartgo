<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Properties\SaveProperty;
use App\Enums\Access\StaffRole;
use App\Enums\Properties\PropertyStatus;
use App\Models\City;
use App\Models\MicroMarket;
use App\Models\Product;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->operations = User::factory()->withRole(StaffRole::OperationsManager)->create();
    $this->noida = City::query()->where('slug', 'noida')->sole();
    $this->sector62 = MicroMarket::query()->where('slug', 'sector-62')->sole();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function propertyData(City $city, ?MicroMarket $sector, array $overrides = []): array
{
    return [
        'name' => 'B-12', 'code' => 'B-12', 'slug' => 'b-12', 'city_id' => $city->id, 'micro_market_id' => $sector?->id,
        'operating_model' => 'owned_operated', 'operational_status' => 'planned', 'opens_on' => null,
        'building' => ['address_line_1' => 'B-12', 'locality' => 'Sector 62', 'postal_code' => null],
        'product_ids' => [],
        ...$overrides,
    ];
}

it('adds a property with its primary building, the categories it offers and an unpublished profile, audited', function () {
    $meetingRooms = Product::query()->where('key', 'meeting_room')->sole();

    $property = app(SaveProperty::class)->handle($this->operations, null, propertyData($this->noida, $this->sector62, ['product_ids' => [(string) $meetingRooms->id]]));

    expect($property)
        ->operational_status->toBe(PropertyStatus::Planned)
        ->and($property->primaryBuilding)->code->toBe('B-12-MAIN')->city_id->toBe($this->noida->id)->is_primary->toBeTrue()
        ->and($property->products->pluck('key')->all())->toBe(['meeting_room'])
        ->and($property->profile()->sole()->isPublished())->toBeFalse()
        ->and(Activity::query()->inLog('properties')->where('event', 'created')->where('subject_type', $property->getMorphClass())->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'offerings_changed')->sole()->properties['added'])->toBe([$meetingRooms->id]);
});

it('keeps the building in the property\'s city when the property moves', function () {
    $property = app(SaveProperty::class)->handle($this->operations, null, propertyData($this->noida, $this->sector62));
    $gurugram = City::query()->where('slug', 'gurugram')->sole();

    app(SaveProperty::class)->handle($this->operations, $property, propertyData($gurugram, null));

    expect($property->fresh()->primaryBuilding->city_id)->toBe($gurugram->id)
        ->and($property->buildings()->count())->toBe(1);
});

it('refuses invalid property details', function (array $overrides, string $field) {
    expect(fn () => app(SaveProperty::class)->handle($this->operations, null, propertyData($this->noida, $this->sector62, $overrides)))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));
})->with([
    'code in lowercase' => [['code' => 'b-12'], 'code'],
    'code already used' => [['code' => 'A-70'], 'code'],
    'address already used in the city' => [['slug' => 'a-70'], 'slug'],
    'sector of another city' => [['micro_market_id' => 999999], 'micro_market_id'],
    'unknown operating model' => [['operating_model' => 'franchise'], 'operating_model'],
    'invalid PIN code' => [['building' => ['address_line_1' => 'B-12', 'locality' => 'Sector 62', 'postal_code' => '01234']], 'building.postal_code'],
    'no address line' => [['building' => ['address_line_1' => '', 'locality' => 'Sector 62']], 'building.address_line_1'],
    'markup in the name' => [['name' => '<b>B-12</b>'], 'name'],
]);

it('lets only operations staff and the Super Admin change properties', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->withRole($role)->create();
    $attempt = fn () => app(SaveProperty::class)->handle($user, null, propertyData($this->noida, $this->sector62));

    $allowed ? expect($attempt())->toBeInstanceOf(Property::class) : expect($attempt)->toThrow(AuthorizationException::class);
})->with([
    'Super Admin' => [StaffRole::SuperAdmin, true],
    'Operations Manager' => [StaffRole::OperationsManager, true],
    'Marketing Manager' => [StaffRole::MarketingManager, false],
    'Property Manager' => [StaffRole::PropertyManager, false],
    'Finance' => [StaffRole::Finance, false],
]);

it('refuses a deactivated account', function () {
    $this->operations->forceFill(['is_active' => false])->save();

    app(SaveProperty::class)->handle($this->operations->fresh(), null, propertyData($this->noida, $this->sector62));
})->throws(AuthorizationException::class);
