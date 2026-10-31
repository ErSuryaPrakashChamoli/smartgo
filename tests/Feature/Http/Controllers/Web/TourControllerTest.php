<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Booking\ChangeTour;
use App\Actions\Booking\ClaimTourSlot;
use App\Enums\Access\StaffRole;
use App\Enums\Booking\TourStatus;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Property;
use App\Models\Revision;
use App\Models\TourAvailabilityRule;
use App\Models\TourRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2027-03-01 04:30:00'); // Monday 1 March 2027, 10:00 in India
    seed(ReferenceDataSeeder::class);
    $this->property = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    $this->notice = Page::factory()->published()->create(['title' => 'Privacy notice', 'slug' => 'legal/privacy', 'template' => 'legal', 'published_revision_id' => Revision::factory()->create(['number' => 3])->id]);
    $this->hours = TourAvailabilityRule::factory()->for($this->property)->window(2, '10:00', '11:00', 30, 1)->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * A valid tour request for Tuesday 2 March 2027 at 10:00 India time, shown long enough ago to be from a person.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tourRequestForTest(Property $property, array $overrides = []): array
{
    return [
        'started' => Crypt::encryptString((string) now()->subSeconds(30)->getTimestamp()),
        'website' => '',
        'property' => $property->id,
        'date' => '2027-03-02',
        'starts_at' => '2027-03-02T04:30:00+00:00',
        'visitors' => '2',
        'name' => 'Asha Verma',
        'email' => 'Asha@Example.com',
        'phone' => '98765 43210',
        'company' => 'Verma Studio',
        'notes' => 'Looking for a cabin for two.',
        'consent' => '1',
        ...$overrides,
    ];
}

it('keeps tour requests closed until the privacy notice is published, and for locations without tour hours', function () {
    $this->notice->forceFill(['status' => 'draft'])->save();

    get('/book-a-tour')->assertOk()->assertSee('Tour times are not offered online yet')->assertDontSee('name="starts_at"', false);
    get($this->property->path())->assertOk()->assertDontSee('Book a tour');
    post('/book-a-tour', tourRequestForTest($this->property))->assertSessionHasErrors('form');

    $this->notice->forceFill(['status' => 'published'])->save();
    $this->hours->delete();

    get('/book-a-tour')->assertOk()->assertSee('Tour times are not offered online yet');
    get($this->property->path())->assertOk()->assertDontSee('Book a tour');
    post('/book-a-tour', tourRequestForTest($this->property))->assertSessionHasErrors('property');

    expect(TourRequest::query()->count())->toBe(0)->and(Lead::query()->count())->toBe(0);
});

it('offers the free times of a day and stores a request held for staff, with a new enquiry, the agreement and its source', function () {
    get($this->property->path())->assertOk()->assertSee('Book a tour')->assertSee('/book-a-tour?property='.$this->property->id, false);
    get('/book-a-tour?property='.$this->property->id)->assertOk()
        ->assertSee('SmartGro A-70')->assertSee('Tue 2 Mar')->assertSee('10:00')->assertSee('10:30')->assertSee('value="2027-03-02T04:30:00+00:00"', false)->assertSee('Privacy notice');

    post('/book-a-tour', tourRequestForTest($this->property, ['utm_source' => 'newsletter', 'from' => '/locations/noida/a-70']))->assertRedirect('/book-a-tour/requested');

    $tour = TourRequest::query()->sole();
    $lead = Lead::query()->sole();

    expect($tour)
        ->status->toBe(TourStatus::Requested)
        ->channel->toBe(TourRequest::CHANNEL_WEBSITE)
        ->created_by->toBeNull()
        ->visitor_count->toBe(2)
        ->notes->toBe('Looking for a cabin for two.')
        ->lead_id->toBe($lead->id)
        ->and($tour->scheduled_start_at->equalTo(CarbonImmutable::parse('2027-03-02 04:30:00')))->toBeTrue()
        ->and($lead)
        ->source->toBe(Lead::SOURCE_TOUR_FORM)
        ->property_id->toBe($this->property->id)
        ->submitted_email->toBe('asha@example.com')
        ->submitted_phone->toBe('+919876543210')
        ->utm_source->toBe('newsletter')
        ->landing_path->toBe('/locations/noida/a-70')
        ->and($lead->consent_snapshot)->toMatchArray(['notice_path' => '/legal/privacy', 'notice_revision' => 3, 'marketing' => false])
        ->and(Activity::query()->where('event', 'tour_requested')->sole()->causer_id)->toBeNull();

    get('/book-a-tour/requested')->assertOk()
        ->assertSee($tour->reference)->assertSee('Tour requested: not yet confirmed')->assertSee('Tuesday 2 March 2027, 10:00 to 10:30 (India time)')
        ->assertSee(e(URL::signedRoute('tours.show', ['reference' => $tour->reference])), false)
        ->assertDontSee('Asha');
});

it('attaches a further request from the same visitor to their open enquiry, and returns the first one for a repeat', function () {
    post('/book-a-tour', tourRequestForTest($this->property));
    post('/book-a-tour', tourRequestForTest($this->property));

    expect(TourRequest::query()->count())->toBe(1);

    post('/book-a-tour', tourRequestForTest($this->property, ['starts_at' => '2027-03-02T05:00:00+00:00', 'email' => 'asha@example.com', 'phone' => '']));

    expect(TourRequest::query()->count())->toBe(2)
        ->and(Lead::query()->count())->toBe(1)
        ->and(TourRequest::query()->distinct()->pluck('lead_id')->all())->toBe([Lead::query()->sole()->id])
        ->and(Activity::query()->where('event', 'lead_tour_requested')->count())->toBe(1);
});

it('refuses a time that was just taken, storing nothing and keeping what the visitor typed', function () {
    TourRequest::factory()->forProperty($this->property)->create(['scheduled_start_at' => '2027-03-02 04:30:00']);
    $leads = Lead::query()->count();

    post('/book-a-tour', tourRequestForTest($this->property))
        ->assertRedirect('/book-a-tour?property='.$this->property->id.'&date=2027-03-02')
        ->assertSessionHasErrors(['starts_at' => ClaimTourSlot::SLOT_TAKEN])
        ->assertSessionHasInput('name', 'Asha Verma');

    get('/book-a-tour?property='.$this->property->id.'&date=2027-03-02')->assertSee('That time is no longer available');

    expect(TourRequest::query()->count())->toBe(1)->and(Lead::query()->count())->toBe($leads);
});

it('stores nothing for the trap field, and refuses a malformed time or a location without tour times', function () {
    $this->withoutMiddleware(ThrottleRequests::class);

    post('/book-a-tour', tourRequestForTest($this->property, ['website' => 'https://spam.example']))->assertRedirect('/book-a-tour/requested');
    post('/book-a-tour', tourRequestForTest($this->property, ['starts_at' => 'tomorrow']))->assertSessionHasErrors(['starts_at' => 'Choose a time for your visit.']);
    post('/book-a-tour', tourRequestForTest($this->property, ['starts_at' => '2027-03-02T04:45:00+00:00']))->assertSessionHasErrors(['starts_at' => ClaimTourSlot::SLOT_TAKEN]);
    post('/book-a-tour', tourRequestForTest($this->property, ['visitors' => '11']))->assertSessionHasErrors('visitors');
    post('/book-a-tour', tourRequestForTest(Property::query()->where('code', 'A-71')->sole()))->assertSessionHasErrors('property');

    expect(TourRequest::query()->count())->toBe(0)->and(Lead::query()->count())->toBe(0);
});

it('shows a visitor\'s tour only through its signed link, lets them move or cancel it, and offers a calendar entry once confirmed', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    post('/book-a-tour', tourRequestForTest($this->property));
    $tour = TourRequest::query()->sole();
    $link = fn (string $route): string => URL::signedRoute($route, ['reference' => $tour->reference]);

    get('/tours/'.$tour->reference)->assertForbidden();
    get($link('tours.show').'x')->assertForbidden();
    get($link('tours.show'))->assertOk()->assertSee($tour->reference)->assertSee('Not confirmed yet')->assertSee('Move my tour')->assertDontSee('Asha')->assertDontSee('asha@example.com');
    get($link('tours.calendar'))->assertNotFound();

    app(SyncAccessControl::class)->handle();
    app(ChangeTour::class)->handle(User::factory()->withRole(StaffRole::SuperAdmin)->create(), $tour, 'confirm', TourStatus::Requested);

    $calendar = get($link('tours.calendar'))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    expect($calendar->getContent())->toContain("DTSTART:20270302T043000Z\r\n")->toContain('DTEND:20270302T050000Z')->toContain('SUMMARY:Tour of SmartGro A-70')->not->toContain('Asha');

    post($link('tours.move'), ['starts_at' => '2027-03-02T05:00:00+00:00'])->assertRedirect($link('tours.show'));

    expect($tour->fresh())->status->toBe(TourStatus::Requested)
        ->and($tour->fresh()->scheduled_start_at->equalTo(CarbonImmutable::parse('2027-03-02 05:00:00')))->toBeTrue();

    post($link('tours.cancel'))->assertRedirect($link('tours.show'));

    expect($tour->fresh())->status->toBe(TourStatus::Cancelled)->cancellation_reason->toBe('Cancelled by the visitor');
    get($link('tours.show'))->assertOk()->assertSee('Cancelled')->assertDontSee('Move my tour');
    post($link('tours.cancel'))->assertSessionHasErrors('tour');
});

it('keeps the visitor\'s details out of the log when storing a tour request fails, and stores nothing', function () {
    $path = tempnam(sys_get_temp_dir(), 'smartgro-log-');
    config(['logging.channels.tour_privacy' => ['driver' => 'single', 'path' => $path], 'logging.default' => 'tour_privacy']);
    $previous = [ini_set('zend.exception_ignore_args', '0'), ini_set('zend.exception_string_param_max_len', '15')];
    Contact::creating(fn (Contact $contact) => $contact->setAttribute('not_a_column', 'x'));

    try {
        post('/book-a-tour', tourRequestForTest($this->property))->assertServerError();
    } finally {
        ini_set('zend.exception_ignore_args', (string) $previous[0]);
        ini_set('zend.exception_string_param_max_len', (string) $previous[1]);
    }

    $written = (string) file_get_contents($path);
    unlink($path);

    expect($written)->toContain('Database query failed')
        ->not->toContain('asha@')->not->toContain('Asha')->not->toContain('Verma')->not->toContain('98765')
        ->and(TourRequest::query()->count())->toBe(0)
        ->and(Lead::query()->count())->toBe(0);
});
