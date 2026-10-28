<?php

use App\Models\Page;
use App\Models\Property;
use App\Models\Revision;
use App\Models\SiteSetting;
use App\Services\Content\PagePayload;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $data
 * @return array<string, list<string>>
 */
function contactBlockErrors(array $data, string $template = 'standard'): array
{
    try {
        app(PagePayload::class)->validate(Page::factory()->create(), ['title' => 'Contact', 'slug' => 'contact', 'template' => $template, 'legal_review_reference' => 'C-1', 'blocks' => [['type' => 'contact', 'data' => $data]]], true);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('stores only its title, introduction and whether to list the locations, never contact details of its own', function () {
    expect(contactBlockErrors(['heading' => 'Talk to us', 'intro' => null, 'show_locations' => true]))->toBe([])
        ->and(contactBlockErrors(['heading' => 'Talk to us', 'phone' => '+91 120 400 0000']))->toHaveKey('blocks.0.data')
        ->and(contactBlockErrors(['heading' => '<b>Talk</b>']))->toHaveKey('blocks.0.data.heading')
        ->and(contactBlockErrors(['heading' => 'Talk to us'], 'legal'))->not->toBe([]);
});

it('shows SmartGro\'s main published contact details and, if chosen, the locations on the website', function () {
    seed(ReferenceDataSeeder::class);
    publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    Page::factory()->published()->create(['slug' => 'contact', 'blocks' => [['type' => 'contact', 'data' => ['heading' => 'Visit or call', 'intro' => null, 'show_locations' => true]]]]);

    get('/contact')->assertOk()
        ->assertSee('Visit or call</h2>', false)
        ->assertSee('Contact details are on their way')
        ->assertSee('Our locations</h2>', false)
        ->assertSee('href="/locations/noida/a-70"', false)
        ->assertDontSee('SmartGro A-71');

    SiteSetting::query()->where('handle', 'contact')->sole()->forceFill(['values' => ['phone' => '+91 120 400 0000', 'email' => 'hello@example.com'], 'published_revision_id' => Revision::factory()->create()->id])->save();

    get('/contact')->assertOk()
        ->assertSee('href="tel:+911204000000"', false)
        ->assertSee('href="mailto:hello@example.com"', false)
        ->assertDontSee('Contact details are on their way');
});

it('leaves the locations out unless chosen', function () {
    seed(ReferenceDataSeeder::class);
    publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    Page::factory()->published()->create(['slug' => 'contact', 'blocks' => [['type' => 'contact', 'data' => ['heading' => null, 'intro' => null, 'show_locations' => false]]]]);

    get('/contact')->assertOk()->assertSee('Talk to SmartGro</h2>', false)->assertDontSee('Our locations');
});

it('never shows contact details that are saved but not published', function () {
    SiteSetting::query()->where('handle', 'contact')->sole()->forceFill(['values' => ['phone' => '+91 120 400 0000'], 'published_revision_id' => null])->save();
    Page::factory()->published()->create(['slug' => 'contact', 'blocks' => [['type' => 'contact', 'data' => ['heading' => null, 'intro' => null, 'show_locations' => false]]]]);

    get('/contact')->assertOk()->assertSee('Contact details are on their way')->assertDontSee('+91 120 400 0000')->assertDontSee('tel:');
});

it('describes a contact page to search engines with the section\'s introduction when no description is written', function () {
    Page::factory()->published()->create(['slug' => 'contact', 'blocks' => [['type' => 'contact', 'data' => ['heading' => null, 'intro' => 'Visit, call or write to SmartGro.', 'show_locations' => false]]]]);

    get('/contact')->assertOk()->assertSee('<meta name="description" content="Visit, call or write to SmartGro.">', false);
});
