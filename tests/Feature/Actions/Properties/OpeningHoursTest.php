<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Properties\SaveProperty;
use App\Enums\Access\StaffRole;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->operations = User::factory()->withRole(StaffRole::OperationsManager)->create();
    $this->property = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
});

/**
 * Saves the property with only its opening hours changed.
 *
 * @param  array<string, mixed>  $hours
 */
function saveHours(User $user, Property $property, array $hours): Property
{
    return app(SaveProperty::class)->handle($user, $property, [
        ...$property->only(['name', 'code', 'slug', 'city_id', 'micro_market_id']),
        'operating_model' => $property->operating_model->value,
        'operational_status' => $property->operational_status->value,
        'building' => $property->primaryBuilding->only(['address_line_1', 'locality']),
        'operating_hours' => $hours,
    ]);
}

it('stores the hours recorded for each day and nothing for days left empty', function () {
    $saved = saveHours($this->operations, $this->property, ['mon' => ['opens' => '09:00:00', 'closes' => '19:30'], 'tue' => ['opens' => null, 'closes' => null]]);

    expect($saved->operating_hours['mon'])->toBe(['opens' => '09:00', 'closes' => '19:30'])
        ->and($saved->operating_hours['tue'])->toBeNull()
        ->and(saveHours($this->operations, $saved, ['mon' => ['opens' => null, 'closes' => null]])->operating_hours)->toBeNull();
});

it('refuses hours that are incomplete or end before they start', function (array $day, string $field) {
    expect(fn () => saveHours($this->operations, $this->property, ['mon' => $day]))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey("operating_hours.mon.{$field}"));
})->with([
    'no closing time' => [['opens' => '09:00', 'closes' => null], 'closes'],
    'no opening time' => [['opens' => null, 'closes' => '18:00'], 'opens'],
    'closes before it opens' => [['opens' => '18:00', 'closes' => '09:00'], 'closes'],
    'not a time' => [['opens' => 'nine', 'closes' => '18:00'], 'opens'],
]);

it('shows the hours on the property page once any day is recorded, other days as closed', function () {
    get('/locations/noida/a-70')->assertOk()->assertDontSee('Opening hours');

    saveHours($this->operations, $this->property, ['mon' => ['opens' => '09:00', 'closes' => '19:00']]);

    get('/locations/noida/a-70')->assertOk()->assertSeeInOrder(['Opening hours', 'Monday', '09:00 to 19:00', 'Tuesday', 'Closed']);
});
