<?php

use App\Enums\Crm\LeadStatus;
use App\Models\City;
use App\Models\Contact;
use App\Models\CustomerSegment;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Product;
use App\Models\Property;
use App\Models\Revision;
use App\Services\Website\EnquiryForm;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(ReferenceDataSeeder::class);
    $this->property = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    $this->notice = Page::factory()->published()->create(['title' => 'Privacy notice', 'slug' => 'legal/privacy', 'template' => 'legal', 'published_revision_id' => Revision::factory()->create(['number' => 3])->id]);
});

/**
 * A valid enquiry, as the form sends it, shown long enough ago to be from a person.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function enquiry(array $overrides = []): array
{
    return [
        'started' => Crypt::encryptString((string) now()->subSeconds(30)->getTimestamp()),
        'website' => '',
        'name' => 'Asha Verma',
        'email' => 'Asha@Example.com',
        'phone' => '98765 43210',
        'company' => 'Verma Studio',
        'team_size' => '2-5',
        'message' => 'Two desks from next month.',
        'consent' => '1',
        ...$overrides,
    ];
}

it('keeps the form closed, and refuses submissions, until a privacy notice is published', function () {
    $this->notice->forceFill(['status' => 'draft'])->save();

    get('/enquire')->assertOk()->assertSee('Online enquiries open soon')->assertDontSee('name="email"', false);
    post('/enquire', enquiry())->assertSessionHasErrors('form');

    expect(Lead::query()->count())->toBe(0);
});

it('stores an enquiry with its published context, a new contact and the agreement given', function () {
    $offering = offerForTest($this->property, 'private_office');
    $segment = CustomerSegment::query()->orderBy('sort_order')->first();

    get('/enquire?property='.$this->property->id.'&workspace=private-cabins')->assertOk()->assertSee('SmartGro A-70, Sector 2, Noida')->assertSee('Privacy notice');

    post('/enquire', enquiry(['property' => $this->property->id, 'workspace' => 'private-cabins', 'segment' => $segment->id, 'utm_source' => 'newsletter', 'from' => '/locations/noida/a-70']))
        ->assertRedirect('/enquire/received');

    $lead = Lead::query()->sole();

    expect($lead)
        ->status->toBe(LeadStatus::New)
        ->owner_user_id->toBeNull()
        ->source->toBe(Lead::SOURCE_WEBSITE_FORM)
        ->property_id->toBe($this->property->id)
        ->product_id->toBe($offering->product_id)
        ->offering_id->toBe($offering->id)
        ->city_id->toBe($this->property->city_id)
        ->customer_segment_id->toBe($segment->id)
        ->submitted_email->toBe('asha@example.com')
        ->submitted_phone->toBe('+919876543210')
        ->utm_source->toBe('newsletter')
        ->landing_path->toBe('/locations/noida/a-70')
        ->and($lead->reference)->toMatch('/^ENQ-[A-HJ-NP-Z2-9]{8}$/')
        ->and($lead->consent_snapshot)->toMatchArray(['purpose' => 'service_communication', 'text' => EnquiryForm::CONSENT_TEXT, 'notice_path' => '/legal/privacy', 'notice_revision' => 3, 'marketing' => false])
        ->and($lead->consent_snapshot['ip_hash'])->not->toContain('127.0.0.1')
        ->and(Contact::query()->sole())->email->toBe('asha@example.com')->phone_e164->toBe('+919876543210');

    get('/enquire/received')->assertOk()->assertSee($lead->reference)->assertSee('This is not a booking')->assertDontSee('asha@example.com')->assertDontSee('Asha Verma');
});

it('links the offering only when it is published at that property', function () {
    offerForTest($this->property, 'virtual_office', published: false);

    post('/enquire', enquiry(['property' => $this->property->id, 'workspace' => 'virtual-office']))->assertRedirect('/enquire/received');

    expect(Lead::query()->sole())->product_id->not->toBeNull()->offering_id->toBeNull();
});

it('refuses invalid details with a message next to each field', function (array $overrides, string $field) {
    post('/enquire', enquiry($overrides))->assertSessionHasErrors($field);

    expect(Lead::query()->count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'markup in the name' => [['name' => '<script>x</script>'], 'name'],
    'neither email nor phone' => [['email' => '', 'phone' => ''], 'email'],
    'email not an address' => [['email' => 'asha@'], 'email'],
    'phone not a number' => [['phone' => '12'], 'phone'],
    'no agreement' => [['consent' => ''], 'consent'],
    'team size not offered' => [['team_size' => '1000'], 'team_size'],
    'message too long' => [['message' => str_repeat('a', 2001)], 'message'],
    'an address on another site' => [['from' => '//evil.example/x'], 'from'],
]);

it('refuses a manipulated location, workspace type or city that is not published', function (Closure $overrides, string $field) {
    post('/enquire', enquiry($overrides()))->assertSessionHasErrors($field);

    expect(Lead::query()->count())->toBe(0);
})->with([
    'location not on the website' => [fn () => ['property' => Property::query()->where('code', 'A-71')->value('id')], 'property'],
    'location that does not exist' => [fn () => ['property' => 999999], 'property'],
    'switched-off workspace type' => [fn () => tap(['workspace' => 'hot-desks'], fn () => Product::query()->where('key', 'hot_desk')->update(['is_active' => false])), 'workspace'],
    'city not open for interest' => [fn () => ['city' => 'delhi'], 'city'],
]);

it('stores nothing for a filled trap field or a form sent faster than a person can', function (Closure $overrides) {
    post('/enquire', enquiry($overrides()))->assertRedirect('/enquire/received');

    expect(Lead::query()->count())->toBe(0)->and(Contact::query()->count())->toBe(0);
    get('/enquire/received')->assertOk()->assertDontSee('Your reference is');
})->with([
    'trap field' => [fn () => ['website' => 'https://spam.example']],
    'sent within a second' => [fn () => ['started' => Crypt::encryptString((string) now()->getTimestamp())]],
]);

it('shows what is missing, rather than a thank-you, when an empty form is sent at once', function () {
    post('/enquire', ['started' => Crypt::encryptString((string) now()->getTimestamp()), 'website' => ''])
        ->assertSessionHasErrors(['name', 'email', 'consent']);
});

it('refuses a form whose timing note is missing or forged', function () {
    post('/enquire', enquiry(['started' => 'forged']))->assertSessionHasErrors('form');

    expect(Lead::query()->count())->toBe(0);
});

it('returns the first enquiry for a repeat within ten minutes, and attaches later ones to the same unchanged contact', function () {
    post('/enquire', enquiry(['property' => $this->property->id]));
    post('/enquire', enquiry(['property' => $this->property->id]));

    expect(Lead::query()->count())->toBe(1);

    $this->travel(11)->minutes();
    post('/enquire', enquiry(['property' => $this->property->id, 'name' => 'A. Verma', 'company' => 'New Co', 'started' => Crypt::encryptString((string) now()->subSeconds(30)->getTimestamp())]));

    expect(Lead::query()->count())->toBe(2)
        ->and(Contact::query()->count())->toBe(1)
        ->and(Contact::query()->sole())->name->toBe('Asha Verma')->company_name->toBe('Verma Studio')
        ->and(Lead::query()->latest('id')->first())->submitted_name->toBe('A. Verma')->submitted_company->toBe('New Co');
});

it('limits enquiries from the same email or phone per day', function () {
    foreach (range(1, 5) as $attempt) {
        $this->travel(11)->minutes();
        post('/enquire', enquiry(['started' => Crypt::encryptString((string) now()->subSeconds(30)->getTimestamp())]))->assertSessionHasNoErrors();
    }

    $this->travel(11)->minutes();
    post('/enquire', enquiry(['started' => Crypt::encryptString((string) now()->subSeconds(30)->getTimestamp())]))->assertSessionHasErrors('form');

    expect(Lead::query()->count())->toBe(5);
});

it('limits submissions from one address per minute', function () {
    foreach (range(1, 3) as $attempt) {
        post('/enquire', enquiry(['email' => "visitor{$attempt}@example.com", 'phone' => '']))->assertRedirect();
    }

    post('/enquire', enquiry(['email' => 'visitor4@example.com', 'phone' => '']))->assertTooManyRequests();
});

it('registers interest in a city that is visible before SmartGro opens there', function () {
    City::query()->where('slug', 'gurugram')->update(['is_publicly_visible' => true]);

    get('/locations/gurugram')->assertOk()->assertSee('Register your interest')->assertSee('name="city" value="gurugram"', false);

    post('/enquire', enquiry(['city' => 'gurugram']))->assertRedirect('/enquire/received');

    expect(Lead::query()->sole())->source->toBe(Lead::SOURCE_CITY_WAITLIST)->city_id->toBe(City::query()->where('slug', 'gurugram')->value('id'))->property_id->toBeNull();
});

it('never writes the visitor\'s details to the log and sends the confirmation page only once', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.json_encode($event->context);
    });

    post('/enquire', enquiry());

    expect(collect($logged)->filter(fn (string $line): bool => str_contains($line, 'asha@example.com') || str_contains($line, '9876543210')))->toBeEmpty();

    get('/enquire/received')->assertOk();
    get('/enquire/received')->assertRedirect('/enquire');
});

it('keeps the visitor\'s details out of the log when storing the enquiry fails', function () {
    $path = tempnam(sys_get_temp_dir(), 'smartgro-log-');
    config(['logging.channels.enquiry_privacy' => ['driver' => 'single', 'path' => $path], 'logging.default' => 'enquiry_privacy']);
    $previous = [ini_set('zend.exception_ignore_args', '0'), ini_set('zend.exception_string_param_max_len', '15')];
    Contact::creating(fn (Contact $contact) => $contact->setAttribute('not_a_column', 'x'));

    try {
        post('/enquire', enquiry())->assertServerError();
    } finally {
        ini_set('zend.exception_ignore_args', (string) $previous[0]);
        ini_set('zend.exception_string_param_max_len', (string) $previous[1]);
    }

    $written = (string) file_get_contents($path);
    unlink($path);

    expect($written)->toContain('Database query failed')
        ->not->toContain('asha@')->not->toContain('Asha')->not->toContain('Verma')->not->toContain('98765')
        ->and(Lead::query()->count())->toBe(0);
});

it('sends no email or notification about an enquiry, so nothing reaches the log mailer', function () {
    Mail::fake();
    Notification::fake();

    post('/enquire', enquiry())->assertRedirect('/enquire/received');

    Mail::assertNothingOutgoing();
    Notification::assertNothingSent();
});
