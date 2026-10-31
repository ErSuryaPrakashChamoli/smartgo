<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Enums\Access\StaffRole;
use App\Http\Controllers\Backoffice\RevisionPreviewController;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Property;
use App\Models\Revision;
use App\Models\User;
use App\Services\Content\PagePayload;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

/**
 * @param  list<array<string, mixed>>  $blocks
 * @return array<string, list<string>>
 */
function enquirySectionErrors(array $blocks, string $template = 'standard'): array
{
    try {
        app(PagePayload::class)->validate(Page::factory()->create(), ['title' => 'Talk to us', 'slug' => 'talk-to-us', 'template' => $template, 'legal_review_reference' => 'C-1', 'blocks' => $blocks], true);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

/**
 * A valid enquiry as the section's form sends it, shown long enough ago to be from a person.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function enquiryFromSection(array $overrides = []): array
{
    return [
        'started' => Crypt::encryptString((string) now()->subSeconds(30)->getTimestamp()),
        'website' => '',
        'name' => 'Asha Verma',
        'email' => 'asha@example.com',
        'consent' => '1',
        'from' => '/talk-to-us',
        'return_to_form' => '1',
        ...$overrides,
    ];
}

beforeEach(function () {
    seed(ReferenceDataSeeder::class);
    $this->property = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    $this->section = ['type' => 'enquiry', 'data' => ['heading' => 'Ask about A-70', 'intro' => 'We reply within a working day.', 'property_id' => $this->property->id, 'workspace' => 'hot-desks']];
});

function publishPrivacyNoticeForTest(): Page
{
    return Page::factory()->published()->create(['title' => 'Privacy notice', 'slug' => 'legal/privacy', 'template' => 'legal', 'published_revision_id' => Revision::factory()->create()->id]);
}

it('stores only its title, introduction, and a location and workspace type chosen from the lists; one per page', function () {
    expect(enquirySectionErrors([$this->section]))->toBe([])
        ->and(enquirySectionErrors([['type' => 'enquiry', 'data' => ['heading' => null, 'intro' => null, 'property_id' => null, 'workspace' => null]]]))->toBe([])
        ->and(enquirySectionErrors([['type' => 'enquiry', 'data' => ['heading' => 'Ask', 'email_to' => 'x@example.com']]]))->toHaveKey('blocks.0.data')
        ->and(enquirySectionErrors([['type' => 'enquiry', 'data' => ['heading' => 'Ask', 'property_id' => 999999]]]))->toHaveKey('blocks.0.data.property_id')
        ->and(enquirySectionErrors([['type' => 'enquiry', 'data' => ['heading' => 'Ask', 'workspace' => 'no-such-type']]]))->toHaveKey('blocks.0.data.workspace')
        ->and(enquirySectionErrors([$this->section, $this->section]))->toHaveKey('blocks')
        ->and(enquirySectionErrors([$this->section], 'legal'))->not->toBe([]);
});

it('shows the Enquire page\'s notice and the published contact details while the form is closed, and no form', function () {
    Page::factory()->published()->create(['slug' => 'talk-to-us', 'blocks' => [$this->section]]);

    get('/talk-to-us')->assertOk()
        ->assertSee('Ask about A-70</h2>', false)
        ->assertSee('Online enquiries open soon')
        ->assertDontSee('name="email"', false)
        ->assertDontSee('action="'.route('enquiries.store').'"', false);
});

it('shows the Enquire page\'s form once it is open, with the chosen location and workspace type, sending from this page', function () {
    publishPrivacyNoticeForTest();
    Page::factory()->published()->create(['slug' => 'talk-to-us', 'blocks' => [$this->section]]);

    $page = get('/talk-to-us?utm_source=newsletter')->assertOk()
        ->assertSee('We reply within a working day.')
        ->assertSee('action="'.route('enquiries.store').'"', false)
        ->assertSee('id="enquiry-form"', false)
        ->assertSee('<option value="'.$this->property->id.'" selected', false)
        ->assertSee('<option value="hot-desks" selected', false)
        ->assertSee('name="from" value="/talk-to-us"', false)
        ->assertSee('name="utm_source" value="newsletter"', false)
        ->assertSee('name="return_to_form" value="1"', false)
        ->assertDontSee('Online enquiries open soon')
        ->assertDontSee('cannot be sent from this preview');

    expect($page->getContent())->not->toMatch('/\sinert[\s>]/');
});

it('stores an enquiry sent from the section with the page it came from, and brings a visitor with errors back to the form', function () {
    publishPrivacyNoticeForTest();
    Page::factory()->published()->create(['slug' => 'talk-to-us', 'blocks' => [$this->section]]);

    from('/talk-to-us')->post('/enquire', enquiryFromSection(['email' => 'not an email']))
        ->assertRedirect(url('/talk-to-us').'#enquiry-form')
        ->assertSessionHasErrors('email');

    from('/talk-to-us')->post('/enquire', enquiryFromSection(['property' => $this->property->id]))->assertRedirect('/enquire/received');

    expect(Lead::query()->sole())->landing_path->toBe('/talk-to-us')->property_id->toBe($this->property->id);
});

it('leaves the Enquire page\'s own errors at the top of that page', function () {
    publishPrivacyNoticeForTest();

    from('/enquire')->post('/enquire', enquiryFromSection(['email' => 'not an email', 'return_to_form' => null]))->assertRedirect(url('/enquire'));
});

it('shows staff the form in a preview as it will appear, but it cannot be sent from there', function () {
    app(SyncAccessControl::class)->handle();
    publishPrivacyNoticeForTest();
    $editor = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $draft = app(CreateDraft::class)->handle(Page::factory()->create(), $editor, ['title' => 'Talk to us', 'slug' => 'talk-to-us', 'template' => 'standard', 'blocks' => [$this->section]]);

    $preview = actingAs($editor)->get(RevisionPreviewController::url($draft))->assertOk()
        ->assertSee('it cannot be sent from this preview')
        ->assertSee('id="enquiry-form"', false);

    expect($preview->getContent())->toMatch('/<div class="[^"]*"\s+inert\s*>\s*<form method="POST"/');
    get('/talk-to-us')->assertNotFound();
});
