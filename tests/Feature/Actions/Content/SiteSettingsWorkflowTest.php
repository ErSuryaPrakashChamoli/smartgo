<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UpdateDraft;
use App\Enums\Access\StaffRole;
use App\Models\Page;
use App\Models\Revision;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    Page::factory()->published()->create(['slug' => 'about']);
});

function setting(string $handle): SiteSetting
{
    return SiteSetting::query()->where('handle', $handle)->sole();
}

/**
 * @param  array<string, mixed>  $values
 */
function approvedSettings(string $handle, array $values, User $author, User $approver): Revision
{
    $revision = app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle(setting($handle), $author, $values), $author);

    return app(ApproveRevision::class)->handle($revision, $approver);
}

it('shows contact details and social links in the footer only once published', function () {
    $contact = approvedSettings('contact', ['phone' => '+91 120 400 0000', 'email' => 'hello@example.com', 'whatsapp' => '+911204000000', 'address' => "Sector 62\nNoida"], $this->author, $this->approver);
    $social = approvedSettings('social', ['linkedin' => 'https://www.linkedin.com/company/example'], $this->author, $this->approver);

    get('/about')->assertOk()->assertDontSee('+91 120 400 0000')->assertDontSee('linkedin.com');

    app(PublishRevision::class)->handle($contact, $this->publisher);
    app(PublishRevision::class)->handle($social, $this->publisher);

    get('/about')
        ->assertOk()
        ->assertSee('href="tel:+911204000000"', false)
        ->assertSee('href="mailto:hello@example.com"', false)
        ->assertSee('href="https://wa.me/911204000000"', false)
        ->assertSee("Sector 62\nNoida", false)
        ->assertSee('href="https://www.linkedin.com/company/example" rel="me noopener"', false);
});

it('refuses values that are not in their expected format', function (string $handle, string $field, string $value) {
    app(CreateDraft::class)->handle(setting($handle), $this->publisher, [$field => $value]);
})->with([
    'phone with markup' => ['contact', 'phone', '<script>1</script>'],
    'email' => ['contact', 'email', 'not-an-email'],
    'WhatsApp without country code' => ['contact', 'whatsapp', '01204000000'],
    'address with markup' => ['contact', 'address', '<b>Noida</b>'],
    'social link to another website' => ['social', 'linkedin', 'https://evil.example/smartgro'],
    'social link without https' => ['social', 'instagram', 'http://instagram.com/smartgro'],
    'social link with script' => ['social', 'facebook', 'javascript:alert(1)'],
    'social link with user details' => ['social', 'x', 'https://user@x.com/smartgro'],
    'analytics ID' => ['tracking', 'ga4_measurement_id', 'UA-12345-1'],
    'pixel ID' => ['tracking', 'meta_pixel_id', 'abc<script>'],
    'GSTIN' => ['legal', 'gstin', '09aaaaa0000a1z5'],
    'CIN' => ['legal', 'cin', 'NOT-A-CIN'],
])->throws(ValidationException::class);

it('lets each group be changed only by people with its permission', function () {
    $finance = User::factory()->withRole(StaffRole::Finance)->create();

    expect(fn () => app(CreateDraft::class)->handle(setting('contact'), $finance, ['phone' => '+91 120 400 0000']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CreateDraft::class)->handle(setting('legal'), $this->author, ['legal_name' => 'Example Private Limited']))->toThrow(AuthorizationException::class);
});

it('needs legal details verified by someone who did not edit them, and put in use by someone other than the verifier', function () {
    $editor = User::factory()->withRole(StaffRole::Finance)->create();
    $verifier = User::factory()->withRole(StaffRole::Finance)->create();
    $draft = app(CreateDraft::class)->handle(setting('legal'), $editor, ['legal_name' => 'Example Private Limited', 'gstin' => '09AAAAA0000A1Z5']);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $editor))->toThrow(ValidationException::class, 'Enter the registered address');

    $draft = app(SubmitRevision::class)->handle(app(UpdateDraft::class)->handle($draft, $editor, [...$draft->payload, 'registered_address' => 'Sector 62, Noida']), $editor);

    expect(fn () => app(ApproveRevision::class)->handle($draft, $editor))->toThrow(AuthorizationException::class);

    $verified = app(ApproveRevision::class)->handle($draft, $verifier);

    expect(fn () => app(PublishRevision::class)->handle($verified, $verifier))->toThrow(AuthorizationException::class);

    app(PublishRevision::class)->handle($verified, $editor);

    expect(setting('legal')->value('legal_name'))->toBe('Example Private Limited');
});

it('never adds tracking IDs or provider scripts to the website, even once published (OD-100)', function (string $path) {
    app(PublishRevision::class)->handle(approvedSettings('tracking', ['ga4_measurement_id' => 'G-ABC1234567', 'gtm_container_id' => 'GTM-ABC1234', 'meta_pixel_id' => '1234567890'], $this->author, $this->approver), $this->publisher);

    $html = get($path)->assertOk()->getContent();
    preg_match_all('/<script\\b[^>]*>/i', $html, $scriptTags);

    expect(setting('tracking')->value('ga4_measurement_id'))->toBe('G-ABC1234567')
        ->and($html)->not->toContain('G-ABC1234567')->not->toContain('GTM-ABC1234')->not->toContain('1234567890')
        ->and(strtolower($html))->not->toContain('googletagmanager')->not->toContain('google-analytics')->not->toContain('gtag(')
        ->not->toContain('datalayer')->not->toContain('fbq(')->not->toContain('connect.facebook.net')
        ->and($scriptTags[0])->each->toMatch('#\\ssrc="'.preg_quote(rtrim(config('app.url'), '/'), '#').'/build/assets/[^"]+\\.js"#');
})->with(['/about', '/insights', '/customers', '/faqs']);
