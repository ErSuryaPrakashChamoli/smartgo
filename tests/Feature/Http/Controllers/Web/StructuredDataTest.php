<?php

use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Post;
use App\Models\Product;
use App\Models\Property;
use App\Models\Revision;
use App\Models\SiteSetting;
use App\Services\Website\Sitemap;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Seo\StructuredDataValidator;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    config(['app.url' => 'https://www.smartgro.example']);
    $this->property = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    offerForTest($this->property, 'private_office');
});

/**
 * The page's schema.org graph, after checking it is the page's only JSON-LD script and has no error.
 *
 * @return list<array<string, mixed>>
 */
function graphForTest(string $path): array
{
    $scripts = StructuredDataValidator::scripts(get($path)->assertOk()->getContent());
    $canonical = 'https://www.smartgro.example'.$path;

    expect($scripts)->toHaveCount(1)
        ->and(StructuredDataValidator::errors($scripts[0], $canonical))->toBe([]);

    return json_decode(html_entity_decode($scripts[0]), true)['@graph'];
}

/**
 * The node of that type in a graph, or null.
 *
 * @param  list<array<string, mixed>>  $graph
 * @return array<string, mixed>|null
 */
function nodeForTest(array $graph, string $type): ?array
{
    return collect($graph)->firstWhere('@type', $type);
}

it('writes one valid graph on every page the sitemap lists, and its breadcrumb steps are canonical pages', function () {
    Post::factory()->published()->create(['slug' => 'choosing-a-workspace', 'title' => 'Choosing a workspace']);
    CaseStudy::factory()->published()->create(['slug' => 'a-team-story']);
    Faq::factory()->published()->create(['question' => 'Is parking available?', 'answer' => 'Ask the front desk.']);
    Faq::factory()->published()->create(['question' => 'Can I visit A-70?', 'answer' => 'Yes, ask for a tour.', 'property_id' => $this->property->id]);
    Faq::factory()->published()->create(['question' => 'Are cabins lockable?', 'answer' => 'Yes.', 'product_id' => Product::query()->where('key', 'private_office')->value('id')]);
    $paths = app(Sitemap::class)->entries()->map(fn (array $entry): string => (string) str($entry['loc'])->after('https://www.smartgro.example')->whenEmpty(fn () => '/'))->all();
    $steps = [];

    expect($paths)->toHaveCount(15);

    foreach ($paths as $path) {
        $graph = graphForTest($path);
        $steps = [...$steps, ...collect(nodeForTest($graph, 'BreadcrumbList')['itemListElement'] ?? [])->pluck('item')->all()];

        expect(nodeForTest($graph, 'Organization'))->not->toBeNull()->and(nodeForTest($graph, 'WebSite'))->not->toBeNull();
    }

    foreach (array_unique($steps) as $step) {
        get((string) str($step)->after('https://www.smartgro.example'))->assertOk()->assertSee('<link rel="canonical" href="'.$step.'">', false);
    }

    expect($steps)->not->toBeEmpty();
});

it('describes a property page as the page shows it: the breadcrumb, the business, its shown hours and its questions only', function () {
    Faq::factory()->published()->create(['question' => 'Can I visit A-70?', 'answer' => 'Yes, ask for a tour.', 'property_id' => $this->property->id]);
    Faq::factory()->create(['question' => 'Draft question?', 'answer' => 'Not published.', 'property_id' => $this->property->id]);
    Faq::factory()->published()->create(['question' => 'A question about another location?', 'answer' => 'Elsewhere.', 'property_id' => Property::query()->where('code', 'A-71')->value('id')]);

    $graph = graphForTest('/locations/noida/a-70');

    expect(nodeForTest($graph, 'BreadcrumbList')['itemListElement'])->toBe([
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Locations', 'item' => 'https://www.smartgro.example/locations'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Noida', 'item' => 'https://www.smartgro.example/locations/noida'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => 'Sector 2', 'item' => 'https://www.smartgro.example/locations/noida/area/sector-2'],
        ['@type' => 'ListItem', 'position' => 4, 'name' => 'SmartGro A-70', 'item' => 'https://www.smartgro.example/locations/noida/a-70'],
    ])->and(nodeForTest($graph, 'FAQPage')['mainEntity'])->toBe([
        ['@type' => 'Question', 'name' => 'Can I visit A-70?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes, ask for a tour.']],
    ])->and(nodeForTest($graph, 'LocalBusiness'))->not->toHaveKey('openingHoursSpecification');

    $this->property->forceFill(['operating_hours' => ['mon' => ['opens' => '09:00', 'closes' => '18:00']]])->save();

    expect(nodeForTest(graphForTest('/locations/noida/a-70'), 'LocalBusiness')['openingHoursSpecification'])
        ->toBe([['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '09:00', 'closes' => '18:00']]);
    get('/locations/noida/a-70')->assertSee('09:00 to 18:00');
});

it('describes an article with its real headline, summary and publication time, written and published by SmartGro', function () {
    $post = Post::factory()->published()->create(['slug' => 'choosing-a-workspace', 'title' => 'Choosing a workspace', 'excerpt' => 'What to look for.', 'published_at' => '2027-02-10 06:30:00']);
    Post::factory()->create(['slug' => 'draft-article']);

    $article = nodeForTest(graphForTest('/insights/choosing-a-workspace'), 'Article');

    expect($article)->toMatchArray([
        'headline' => 'Choosing a workspace',
        'description' => 'What to look for.',
        'datePublished' => '2027-02-10T06:30:00+00:00',
        'url' => 'https://www.smartgro.example/insights/choosing-a-workspace',
        'author' => ['@id' => 'https://www.smartgro.example/#organization'],
    ])->and($post->published_at->toAtomString())->toBe('2027-02-10T06:30:00+00:00');
    get('/insights/draft-article')->assertNotFound();
    expect(nodeForTest(graphForTest('/insights'), 'Article'))->toBeNull();
});

it('gives SmartGro its approved name and logo, and its contact details and social links only once published', function () {
    $organization = nodeForTest(graphForTest('/'), 'Organization');

    expect($organization)->toBe([
        '@type' => 'Organization', '@id' => 'https://www.smartgro.example/#organization', 'name' => 'SmartGro', 'url' => 'https://www.smartgro.example/',
        'logo' => ['@type' => 'ImageObject', 'url' => 'https://www.smartgro.example/brand/smartgro-logo.webp', 'width' => 388, 'height' => 218],
    ])->and(file_exists(public_path('brand/smartgro-logo.webp')))->toBeTrue();

    SiteSetting::query()->where('handle', 'contact')->sole()->forceFill(['values' => ['phone' => '+91 120 400 0000', 'email' => 'hello@example.com'], 'published_revision_id' => Revision::factory()->create()->id])->save();
    SiteSetting::query()->where('handle', 'social')->sole()->forceFill(['values' => ['linkedin' => 'https://www.linkedin.com/company/example'], 'published_revision_id' => Revision::factory()->create()->id])->save();

    expect(nodeForTest(graphForTest('/'), 'Organization'))->toMatchArray([
        'email' => 'hello@example.com', 'telephone' => '+91 120 400 0000', 'sameAs' => ['https://www.linkedin.com/company/example'],
    ]);
});

it('writes nothing on pages that are not indexed, and nothing a question\'s text could break out of', function () {
    Faq::factory()->published()->create(['question' => 'Is it safe?', 'answer' => '</script><script>alert(1)</script>']);

    foreach (['/locations/compare', '/insights', '/customers'] as $path) {
        expect(StructuredDataValidator::scripts(get($path)->getContent()))->toBe([]);
    }

    $html = get('/faqs')->assertOk()->getContent();

    expect(StructuredDataValidator::scripts($html))->toHaveCount(1)
        ->and($html)->not->toContain('"text":"</script>')
        ->and(nodeForTest(graphForTest('/faqs'), 'FAQPage')['mainEntity'][0]['acceptedAnswer']['text'])->toBe('</script><script>alert(1)</script>');
});
