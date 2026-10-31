<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Booking\ChangeTour;
use App\Actions\Booking\ClaimTourSlot;
use App\Actions\Booking\SaveClosure;
use App\Actions\Booking\SaveTourRule;
use App\Actions\Booking\ScheduleTour;
use App\Enums\Access\StaffRole;
use App\Enums\Booking\TourStatus;
use App\Events\Booking\TourScheduled;
use App\Models\Lead;
use App\Models\Property;
use App\Models\TourAvailabilityRule;
use App\Models\TourRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2027-03-01 04:30:00'); // Monday 1 March 2027, 10:00 in India
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->other = Property::query()->where('code', 'A-71')->sole();
    $this->sales = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $this->sales->assignedProperties()->attach($this->property);
    $this->operations = User::factory()->withRole(StaffRole::OperationsManager)->create();
    $this->lead = Lead::factory()->forProperty($this->property)->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * The first validation message of the attempt, or null when it passes.
 */
function tourErrorForTest(Closure $attempt): ?string
{
    try {
        $attempt();
    } catch (ValidationException $exception) {
        return collect($exception->errors())->flatten()->first();
    }

    return null;
}

/**
 * Tuesday tour hours at the property: 10:00 to 11:00 India time, 30-minute tours, one at a time.
 */
function tuesdayHoursForTest(Property $property, int $atOnce = 1): TourAvailabilityRule
{
    return TourAvailabilityRule::factory()->for($property)->window(2, '10:00', '11:00', 30, $atOnce)->create();
}

it('lets only those who manage a property\'s tours set its tour hours, and refuses overlapping or too short windows', function () {
    $hours = ['weekday' => 2, 'start_time' => '10:00', 'end_time' => '12:00', 'slot_minutes' => 30, 'max_concurrent_tours' => 2];
    $rule = app(SaveTourRule::class)->handle($this->sales, $this->property, null, $hours);

    expect($rule->summary())->toBe('Tuesday, 10:00 to 12:00: 30-minute tours, up to 2 at a time')
        ->and(Activity::query()->where('event', 'tour_hours_added')->sole()->properties['new'])->toBe($rule->summary())
        ->and(fn () => app(SaveTourRule::class)->handle($this->operations, $this->property, null, $hours))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveTourRule::class)->handle($this->sales, $this->other, null, $hours))->toThrow(AuthorizationException::class)
        ->and(tourErrorForTest(fn () => app(SaveTourRule::class)->handle($this->sales, $this->property, null, [...$hours, 'start_time' => '11:30', 'end_time' => '13:00'])))->toBe('These hours overlap other tour hours on Tuesday. Change those instead.')
        ->and(tourErrorForTest(fn () => app(SaveTourRule::class)->handle($this->sales, $this->property, null, [...$hours, 'weekday' => 3, 'end_time' => '10:20'])))->toBe('The window is shorter than one tour.')
        ->and(tourErrorForTest(fn () => app(SaveTourRule::class)->handle($this->sales, $this->property, $rule, [...$hours, 'end_time' => '13:00'])))->toBeNull()
        ->and(TourAvailabilityRule::query()->count())->toBe(1);
});

it('records closures with the property\'s management permission and reports the tours inside without changing them', function () {
    tuesdayHoursForTest($this->property);
    $tour = app(ScheduleTour::class)->handle($this->sales, $this->lead, ['property_id' => $this->property->id, 'starts_at' => '2027-03-02T04:30:00+00:00', 'visitors' => 2]);
    $closure = ['starts_at' => '2027-03-02 00:00', 'ends_at' => '2027-03-02 23:59', 'reason' => 'Public holiday'];

    $result = app(SaveClosure::class)->handle($this->operations, $this->property, null, $closure);

    expect($result['tours_inside'])->toBe(1)
        ->and($result['closure']->starts_at->equalTo(CarbonImmutable::parse('2027-03-01 18:30:00')))->toBeTrue()
        ->and($tour->fresh()->status)->toBe(TourStatus::Confirmed)
        ->and(app(TourRequest::class)->newQuery()->count())->toBe(1)
        ->and(Activity::query()->where('event', 'closure_added')->sole()->properties['tours_inside'])->toBe(1)
        ->and(fn () => app(SaveClosure::class)->handle($this->sales, $this->property, null, $closure))->toThrow(AuthorizationException::class)
        ->and(tourErrorForTest(fn () => app(SaveClosure::class)->handle($this->operations, $this->property, null, [...$closure, 'starts_at' => '2027-02-01 00:00', 'ends_at' => '2027-02-02 00:00'])))->toContain('ends in the future');
});

it('arranges a confirmed tour from an enquiry for a free time, within the user\'s scope (UJ-10)', function () {
    Event::fake([TourScheduled::class]);
    tuesdayHoursForTest($this->property);
    $data = ['property_id' => $this->property->id, 'starts_at' => '2027-03-02T04:30:00+00:00', 'visitors' => 3, 'notes' => 'Wants a cabin for 3'];

    $tour = app(ScheduleTour::class)->handle($this->sales, $this->lead, $data);

    expect($tour)
        ->status->toBe(TourStatus::Confirmed)
        ->channel->toBe(TourRequest::CHANNEL_STAFF)
        ->created_by->toBe($this->sales->id)
        ->confirmed_by->toBe($this->sales->id)
        ->duration_minutes->toBe(30)
        ->visitor_count->toBe(3)
        ->and($tour->reference)->toMatch('/^TOUR-[A-HJ-NP-Z2-9]{8}$/')
        ->and(Activity::query()->where('event', 'tour_scheduled')->sole()->properties->only(['lead', 'property', 'channel'])->all())
        ->toEqual(['lead' => $this->lead->reference, 'property' => 'A-70', 'channel' => 'staff'])
        ->and(tourErrorForTest(fn () => app(ScheduleTour::class)->handle($this->sales, $this->lead, $data)))->toBe(ClaimTourSlot::SLOT_TAKEN)
        ->and(fn () => app(ScheduleTour::class)->handle($this->operations, $this->lead, [...$data, 'starts_at' => '2027-03-02T05:00:00+00:00']))->toThrow(AuthorizationException::class)
        ->and(TourRequest::query()->count())->toBe(1);
    Event::assertDispatchedTimes(TourScheduled::class, 1);

    $manager = User::factory()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->property);

    expect(app(ScheduleTour::class)->handle($manager, $this->lead, [...$data, 'starts_at' => '2027-03-02T05:00:00+00:00'])->created_by)->toBe($manager->id);
});

it('confirms, moves, cancels and records outcomes, refusing a stale screen, another scope and the wrong moment', function () {
    tuesdayHoursForTest($this->property, atOnce: 1);
    $request = TourRequest::factory()->forProperty($this->property)->create(['scheduled_start_at' => '2027-03-02 04:30:00']);
    $change = fn (?User $user, string $what, TourStatus $expected, array $data = []) => app(ChangeTour::class)->handle($user, $request->fresh(), $what, $expected, $data);

    expect(fn () => $change($this->operations, 'confirm', TourStatus::Requested))->toThrow(AuthorizationException::class)
        ->and($change($this->sales, 'confirm', TourStatus::Requested)->status)->toBe(TourStatus::Confirmed)
        ->and(tourErrorForTest(fn () => $change($this->sales, 'confirm', TourStatus::Requested)))->toBe('This tour was changed meanwhile. Reload it and try again.')
        ->and(tourErrorForTest(fn () => $change($this->sales, 'reschedule', TourStatus::Confirmed, ['starts_at' => '2027-03-02T04:45:00+00:00'])))->toBe(ClaimTourSlot::SLOT_TAKEN)
        ->and($change($this->sales, 'reschedule', TourStatus::Confirmed, ['starts_at' => '2027-03-02T05:00:00+00:00'])->scheduled_start_at->equalTo(CarbonImmutable::parse('2027-03-02 05:00:00')))->toBeTrue()
        ->and($request->fresh()->status)->toBe(TourStatus::Confirmed)
        ->and(tourErrorForTest(fn () => $change($this->sales, 'complete', TourStatus::Confirmed)))->toBe('The outcome can be recorded once a confirmed tour has started.')
        ->and(tourErrorForTest(fn () => $change($this->sales, 'assign_host', TourStatus::Confirmed, ['host_user_id' => User::factory()->withRole(StaffRole::MarketingManager)->create()->id])))->toContain('Choose an active member of staff')
        ->and($change($this->sales, 'assign_host', TourStatus::Confirmed, ['host_user_id' => $this->operations->id])->host_user_id)->toBe($this->operations->id);

    CarbonImmutable::setTestNow('2027-03-02 05:10:00');

    expect(tourErrorForTest(fn () => $change($this->sales, 'cancel', TourStatus::Confirmed, ['reason' => 'Too late'])))->toBe('Only a tour that has not started can be cancelled.')
        ->and($change($this->sales, 'no_show', TourStatus::Confirmed)->status)->toBe(TourStatus::NoShow)
        ->and(Activity::query()->where('subject_type', $request->getMorphClass())->where('subject_id', $request->id)->orderBy('id')->pluck('event')->all())
        ->toBe(['tour_confirmed', 'tour_rescheduled', 'tour_host_assigned', 'tour_no_show']);

    $later = TourRequest::factory()->forProperty($this->property)->confirmed()->create(['scheduled_start_at' => '2027-03-09 04:30:00']);

    expect(tourErrorForTest(fn () => app(ChangeTour::class)->handle($this->sales, $later, 'cancel', TourStatus::Confirmed)))->toBe('Give the reason for cancelling.')
        ->and(app(ChangeTour::class)->handle($this->sales, $later, 'cancel', TourStatus::Confirmed, ['reason' => 'Visitor found another space'])->cancellation_reason)->toBe('Visitor found another space');
});

it('lets a visitor only move or cancel their own upcoming tour, and a time they pick waits for staff again', function () {
    tuesdayHoursForTest($this->property, atOnce: 1);
    $tour = TourRequest::factory()->forProperty($this->property)->confirmed()->create(['scheduled_start_at' => '2027-03-02 04:30:00']);

    expect(fn () => app(ChangeTour::class)->handle(null, $tour, 'confirm', TourStatus::Confirmed))->toThrow(AuthorizationException::class);

    $moved = app(ChangeTour::class)->handle(null, $tour, 'reschedule', TourStatus::Confirmed, ['starts_at' => '2027-03-02T05:00:00+00:00']);

    expect($moved->status)->toBe(TourStatus::Requested)
        ->and($moved->confirmed_at)->toBeNull()
        ->and(Activity::query()->where('event', 'tour_rescheduled')->sole())->causer_id->toBeNull()->description->toBe('Tour moved by the visitor');

    config(['tours.confirm_on_request' => true]);

    expect(app(ChangeTour::class)->handle(null, $moved, 'reschedule', TourStatus::Requested, ['starts_at' => '2027-03-02T04:30:00+00:00'])->status)->toBe(TourStatus::Requested)
        ->and(app(ChangeTour::class)->handle(null, $moved->fresh(), 'cancel', TourStatus::Requested, ['reason' => 'Cancelled by the visitor'])->status)->toBe(TourStatus::Cancelled);
});
