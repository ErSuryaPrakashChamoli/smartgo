<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Services\Crm\FirstResponseClock;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->clock = app(FirstResponseClock::class);
});

/**
 * An enquiry about A-70 that arrived at the given India time, with an optional first response.
 */
function enquiryArrivedAt(string $indiaTime, ?string $respondedIndiaTime = null, ?Property $property = null): Lead
{
    $toUtc = fn (string $time): CarbonImmutable => CarbonImmutable::parse($time, 'Asia/Kolkata')->utc();

    return Lead::factory()->forProperty($property ?? test()->property)->create([
        'created_at' => $toUtc($indiaTime),
        'first_responded_at' => $respondedIndiaTime === null ? null : $toUtc($respondedIndiaTime),
    ]);
}

/**
 * A target set by the test only: the owner has not decided the minutes or the hours (D-09, A-41).
 */
function firstResponseTarget(int $minutes, string $clock): void
{
    config(['crm.first_response.target_minutes' => $minutes, 'crm.first_response.clock' => $clock]);
}

it('has no target, no due time and nothing overdue until the owner sets both the minutes and the hours (A-41)', function () {
    $lead = enquiryArrivedAt('2026-11-02 10:00');
    $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00', 'Asia/Kolkata'));

    expect(config('crm.first_response'))->toBe(['target_minutes' => null, 'clock' => null])
        ->and($this->clock->hasTarget())->toBeFalse()
        ->and($this->clock->dueAt($lead))->toBeNull()
        ->and($this->clock->isOverdue($lead))->toBeFalse()
        ->and($this->clock->overdueLeadIds(User::factory()->withRole(StaffRole::SuperAdmin)->create()))->toBe([]);

    config(['crm.first_response.target_minutes' => 15]);
    expect($this->clock->hasTarget())->toBeFalse();

    config(['crm.first_response.target_minutes' => null, 'crm.first_response.clock' => FirstResponseClock::CALENDAR]);
    expect($this->clock->hasTarget())->toBeFalse();
});

it('measures the wait in plain time since arrival, and stops at the first response', function () {
    $waiting = enquiryArrivedAt('2026-11-09 10:00');
    $answered = enquiryArrivedAt('2026-11-09 10:00', '2026-11-09 10:35');
    $this->travelTo(CarbonImmutable::parse('2026-11-09 12:15', 'Asia/Kolkata'));

    expect($this->clock->waitedMinutes($waiting))->toBe(135)
        ->and($this->clock->waitedMinutes($answered))->toBe(35);
});

it('writes a wait as staff read it', function (int $minutes, string $text) {
    expect($this->clock->describe($minutes))->toBe($text);
})->with([[8, '8 min'], [60, '1 h'], [135, '2 h 15 min'], [1440, '1 d'], [4560, '3 d 4 h']]);

it('counts a calendar target in every minute (mechanism; the target is set by this test)', function () {
    firstResponseTarget(15, FirstResponseClock::CALENDAR);
    $lead = enquiryArrivedAt('2026-11-07 20:00');

    expect($this->clock->dueAt($lead)->equalTo(CarbonImmutable::parse('2026-11-07 20:15', 'Asia/Kolkata')))->toBeTrue()
        ->and($this->clock->isOverdue($lead, CarbonImmutable::parse('2026-11-07 20:14', 'Asia/Kolkata')))->toBeFalse()
        ->and($this->clock->isOverdue($lead, CarbonImmutable::parse('2026-11-07 20:16', 'Asia/Kolkata')))->toBeTrue()
        ->and($this->clock->respondedLate(enquiryArrivedAt('2026-11-07 20:00', '2026-11-07 20:20')))->toBeTrue()
        ->and($this->clock->respondedLate(enquiryArrivedAt('2026-11-07 20:00', '2026-11-07 20:10')))->toBeFalse()
        ->and($this->clock->isOverdue(enquiryArrivedAt('2026-11-07 20:00', '2026-11-07 21:00'), CarbonImmutable::parse('2026-11-08 10:00', 'Asia/Kolkata')))->toBeFalse();
});

it('counts a location-hours target only in the location\'s opening hours, India time (mechanism; set by this test)', function (string $arrived, string $due) {
    firstResponseTarget(15, FirstResponseClock::LOCATION_HOURS);
    $weekdays = ['opens' => '09:00', 'closes' => '18:00'];
    $this->property->forceFill(['operating_hours' => ['mon' => $weekdays, 'tue' => $weekdays, 'wed' => $weekdays, 'thu' => $weekdays, 'fri' => $weekdays]])->save();

    expect($this->clock->dueAt(enquiryArrivedAt($arrived))->setTimezone('Asia/Kolkata')->format('D Y-m-d H:i'))->toBe($due);
})->with([
    'during opening hours' => ['2026-11-09 11:00', 'Mon 2026-11-09 11:15'],
    'before opening' => ['2026-11-10 07:00', 'Tue 2026-11-10 09:15'],
    'just before closing, so it carries over' => ['2026-11-10 17:55', 'Wed 2026-11-11 09:10'],
    'on a closed weekend' => ['2026-11-07 20:00', 'Mon 2026-11-09 09:15'],
]);

it('gives no due time by location hours to an enquiry without a location, or at a location without hours', function () {
    firstResponseTarget(15, FirstResponseClock::LOCATION_HOURS);
    $this->property->forceFill(['operating_hours' => null])->save();
    $general = Lead::factory()->create(['property_id' => null, 'created_at' => now()->subDay()]);

    expect($this->clock->dueAt(enquiryArrivedAt('2026-11-09 11:00')))->toBeNull()
        ->and($this->clock->dueAt($general))->toBeNull()
        ->and($this->clock->isOverdue($general))->toBeFalse();
});

it('lists only the overdue enquiries the user may see', function () {
    firstResponseTarget(15, FirstResponseClock::CALENDAR);
    $sales = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $sales->assignedProperties()->attach($this->property);
    $overdue = enquiryArrivedAt('2026-11-09 10:00');
    enquiryArrivedAt('2026-11-09 10:00', '2026-11-09 10:05');
    enquiryArrivedAt('2026-11-09 10:00', property: Property::query()->where('code', 'H-105')->sole());
    $this->travelTo(CarbonImmutable::parse('2026-11-09 11:00', 'Asia/Kolkata'));

    expect($this->clock->overdueLeadIds($sales))->toBe([$overdue->id]);
});
