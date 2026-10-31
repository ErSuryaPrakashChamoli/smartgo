<?php

use App\Enums\Booking\TourStatus;
use App\Models\Property;
use App\Models\PropertyClosure;
use App\Models\TourAvailabilityRule;
use App\Models\TourRequest;
use App\Services\Booking\TourSlots;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2027-03-01 04:30:00'); // Monday 1 March 2027, 10:00 in India
    $this->seed(ReferenceDataSeeder::class);
    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->slots = app(TourSlots::class);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * An instant given in India time, e.g. ist('2027-03-02 10:00').
 */
function ist(string $time): CarbonImmutable
{
    return CarbonImmutable::parse($time, 'Asia/Kolkata')->utc();
}

it('offers the times of the tour hours in India time, only in the future and within the window', function () {
    TourAvailabilityRule::factory()->for($this->property)->window(1, '09:00', '12:00', 60, 1)->create();
    TourAvailabilityRule::factory()->for($this->property)->window(2, '10:00', '11:00', 30, 1)->create();

    $times = $this->slots->bookable($this->property)->map(fn (array $slot): string => $slot['starts_at']->timezone('Asia/Kolkata')->format('D j M H:i'))->all();

    expect($times)->toBe([
        'Mon 1 Mar 11:00',
        'Tue 2 Mar 10:00', 'Tue 2 Mar 10:30',
        'Mon 8 Mar 09:00', 'Mon 8 Mar 10:00', 'Mon 8 Mar 11:00',
        'Tue 9 Mar 10:00', 'Tue 9 Mar 10:30',
    ])
        ->and($this->slots->bookable($this->property)->first()['starts_at']->equalTo(CarbonImmutable::parse('2027-03-01 05:30:00')))->toBeTrue()
        ->and($this->slots->bookable($this->property)->first()['minutes'])->toBe(60);
});

it('marks a time full once as many tours as allowed overlap it, frees cancelled places, and leaves out closures', function () {
    TourAvailabilityRule::factory()->for($this->property)->window(2, '10:00', '11:00', 30, 2)->create();
    TourRequest::factory()->forProperty($this->property)->create(['scheduled_start_at' => ist('2027-03-02 10:00')]);
    $confirmed = TourRequest::factory()->forProperty($this->property)->confirmed()->create(['scheduled_start_at' => ist('2027-03-02 09:45'), 'duration_minutes' => 30]);
    TourRequest::factory()->forProperty($this->property)->create(['scheduled_start_at' => ist('2027-03-02 10:30'), 'status' => TourStatus::Cancelled]);
    PropertyClosure::factory()->for($this->property)->create(['starts_at' => ist('2027-03-09 00:00'), 'ends_at' => ist('2027-03-10 00:00')]);

    $free = $this->slots->bookable($this->property)->mapWithKeys(fn (array $slot): array => [$slot['starts_at']->timezone('Asia/Kolkata')->format('j M H:i') => $slot['free']])->all();

    expect($free)->toBe(['2 Mar 10:00' => false, '2 Mar 10:30' => true])
        ->and($this->slots->freeSlotAt($this->property, ist('2027-03-02 10:00')))->toBeNull()
        ->and($this->slots->freeSlotAt($this->property, ist('2027-03-02 10:30')))->not->toBeNull()
        ->and($this->slots->freeSlotAt($this->property, ist('2027-03-02 10:15')))->toBeNull()
        ->and($this->slots->freeSlotAt($this->property, ist('2027-03-02 10:00'), ignoring: $confirmed))->not->toBeNull()
        ->and($this->slots->nextFree($this->property, ist('2027-03-01 12:00'))->map(fn (array $slot): string => $slot['starts_at']->toAtomString())->all())->toBe([ist('2027-03-02 10:30')->toAtomString()]);
});

it('offers nothing for a property without tour hours', function () {
    expect($this->slots->bookable($this->property))->toBeEmpty()
        ->and($this->slots->hasHours($this->property))->toBeFalse()
        ->and($this->slots->options($this->property))->toBe([]);
});
