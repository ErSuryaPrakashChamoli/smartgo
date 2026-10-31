<?php

use App\Models\City;
use App\Models\Page;
use App\Models\Property;
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
function locationListErrors(array $data, string $template = 'standard'): array
{
    try {
        app(PagePayload::class)->validate(Page::factory()->create(), ['title' => 'Our spaces', 'slug' => 'our-spaces', 'template' => $template, 'legal_review_reference' => 'C-1', 'blocks' => [['type' => 'locations', 'data' => $data]]], true);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

beforeEach(function () {
    seed(ReferenceDataSeeder::class);
    $this->noida = City::query()->where('slug', 'noida')->sole();
});

it('stores only its title, introduction and a city chosen from the list', function () {
    expect(locationListErrors(['heading' => 'Where to find us', 'intro' => null, 'city_id' => $this->noida->id]))->toBe([])
        ->and(locationListErrors(['heading' => null, 'intro' => null, 'city_id' => null]))->toBe([])
        ->and(locationListErrors(['heading' => 'Where', 'property_ids' => [1]]))->toHaveKey('blocks.0.data')
        ->and(locationListErrors(['heading' => '<b>Where</b>']))->toHaveKey('blocks.0.data.heading')
        ->and(locationListErrors(['heading' => 'Where', 'city_id' => 999999]))->toHaveKey('blocks.0.data.city_id')
        ->and(locationListErrors(['heading' => 'Where'], 'legal'))->not->toBe([]);
});

it('lists the locations on the website as cards linking to their pages, never one that is not published', function () {
    publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    publishPropertyProfileForTest(Property::query()->where('code', 'H-105')->sole());
    Page::factory()->published()->create(['slug' => 'our-spaces', 'blocks' => [['type' => 'locations', 'data' => ['heading' => null, 'intro' => 'Two centres in Noida.', 'city_id' => null]]]]);

    get('/our-spaces')->assertOk()
        ->assertSee('Our locations</h2>', false)
        ->assertSee('Two centres in Noida.')
        ->assertSeeInOrder(['href="/locations/noida/a-70"', 'href="/locations/noida/h-105"'], false)
        ->assertDontSee('SmartGro A-71')
        ->assertDontSee('SmartGro A-23');
});

it('lists one city\'s locations when a city is chosen, under that city\'s name', function () {
    publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    Page::factory()->published()->create(['slug' => 'noida-spaces', 'blocks' => [
        ['type' => 'locations', 'data' => ['heading' => null, 'intro' => null, 'city_id' => $this->noida->id]],
        ['type' => 'locations', 'data' => ['heading' => 'In Gurugram', 'intro' => null, 'city_id' => City::query()->where('slug', 'gurugram')->value('id')]],
    ]]);

    get('/noida-spaces')->assertOk()
        ->assertSee('SmartGro in Noida</h2>', false)
        ->assertSee('href="/locations/noida/a-70"', false)
        ->assertDontSee('In Gurugram');
});

it('follows each location\'s own publication, and leaves the section out when none is left', function () {
    $property = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    Page::factory()->published()->create(['slug' => 'our-spaces', 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => 'About SmartGro', 'body' => '<p>Flexible workspaces.</p>']],
        ['type' => 'locations', 'data' => ['heading' => null, 'intro' => null, 'city_id' => null]],
    ]]);

    get('/our-spaces')->assertOk()->assertSee('href="/locations/noida/a-70"', false);

    $property->profile->forceFill(['status' => 'unpublished'])->save();

    get('/our-spaces')->assertOk()->assertSee('About SmartGro')->assertDontSee('Our locations')->assertDontSee('/locations/noida/a-70');
});

it('lists nothing for a city that no longer exists, rather than every location', function () {
    publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    Page::factory()->published()->create(['slug' => 'our-spaces', 'blocks' => [['type' => 'locations', 'data' => ['heading' => null, 'intro' => null, 'city_id' => 999999]]]]);

    get('/our-spaces')->assertOk()->assertDontSee('/locations/noida/a-70');
});
