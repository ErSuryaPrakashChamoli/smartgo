<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Booking\ClaimTourSlot;
use App\Enums\Access\StaffRole;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Property;
use App\Models\TourAvailabilityRule;
use App\Models\TourRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\Concurrency\PublishingHarness;

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
    PublishingHarness::reset();
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    $this->property = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);

    $tomorrow = CarbonImmutable::now('Asia/Kolkata')->addDay();
    TourAvailabilityRule::factory()->for($this->property)->window($tomorrow->isoWeekday(), '10:00', '10:30', 30, 1)->create();
    $this->request = fn (string $email): array => [
        'started' => Crypt::encryptString((string) now()->subMinute()->getTimestamp()),
        'property' => $this->property->id,
        'starts_at' => $tomorrow->setTime(10, 0)->utc()->toAtomString(),
        'visitors' => 1,
        'name' => 'Test visitor',
        'email' => $email,
        'consent' => '1',
    ];
});

afterEach(function () {
    PublishingHarness::reset();
});

it('lets exactly one of two visitors take the last place on a tour at the same instant (BR-TR-2)', function () {
    $results = PublishingHarness::runWorkers([
        ['mode' => 'request_tour', 'enquiry' => ($this->request)('first@example.com')],
        ['mode' => 'request_tour', 'enquiry' => ($this->request)('second@example.com')],
    ]);

    expect(collect($results)->where('outcome', 'refused')->pluck('message')->all())->toBe([ClaimTourSlot::SLOT_TAKEN])
        ->and(TourRequest::query()->count())->toBe(1)
        ->and(collect($results)->pluck('outcome')->contains(TourRequest::query()->sole()->reference))->toBeTrue()
        ->and(Lead::query()->count())->toBe(1);
})->group('mysql', 'concurrency');

it('lets exactly one of two staff arrange a tour in the last place at the same instant (BR-TR-2)', function () {
    $first = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $second = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $first->assignedProperties()->attach($this->property);
    $second->assignedProperties()->attach($this->property);
    $tour = ['property_id' => $this->property->id, 'starts_at' => ($this->request)('x@example.com')['starts_at'], 'visitors' => 1];

    $results = PublishingHarness::runWorkers([
        ['mode' => 'schedule_tour', 'user_id' => $first->id, 'lead_id' => Lead::factory()->forProperty($this->property)->create()->id, 'enquiry' => $tour],
        ['mode' => 'schedule_tour', 'user_id' => $second->id, 'lead_id' => Lead::factory()->forProperty($this->property)->create()->id, 'enquiry' => $tour],
    ]);

    expect(collect($results)->where('outcome', 'refused')->pluck('message')->all())->toBe([ClaimTourSlot::SLOT_TAKEN])
        ->and(TourRequest::query()->count())->toBe(1);
})->group('mysql', 'concurrency');

it('stores one tour, one enquiry and one contact when the same request is sent twice at the same instant', function () {
    $request = ($this->request)('asha@example.com');

    $results = PublishingHarness::runWorkers([
        ['mode' => 'request_tour', 'enquiry' => $request],
        ['mode' => 'request_tour', 'enquiry' => $request],
    ]);

    expect(TourRequest::query()->count())->toBe(1)
        ->and(Lead::query()->count())->toBe(1)
        ->and(Contact::query()->count())->toBe(1)
        ->and(collect($results)->pluck('outcome')->unique()->all())->toBe([TourRequest::query()->sole()->reference]);
})->group('mysql', 'concurrency');
