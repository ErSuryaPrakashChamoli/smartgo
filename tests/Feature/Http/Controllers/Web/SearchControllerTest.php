<?php

use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Property;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(ReferenceDataSeeder::class);
});

it('finds what the website shows, grouped by kind, each with its address', function () {
    $a70 = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    offerForTest($a70, 'meeting_room');
    Page::factory()->published()->create(['title' => 'Meeting space guide', 'slug' => 'meeting-space-guide', 'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>How to plan a meeting.</p>']]]]);
    Post::factory()->published()->create(['title' => 'Running better meetings', 'slug' => 'running-better-meetings', 'excerpt' => 'Short meetings work.']);
    Faq::factory()->published()->create(['question' => 'Can I book a meeting room by the hour?', 'answer' => 'Ask us.']);
    CaseStudy::factory()->published()->create(['title' => 'A team that meets weekly', 'slug' => 'team-meets-weekly', 'summary' => 'Weekly meetings.']);

    get('/search?q=meeting')
        ->assertOk()
        ->assertSeeInOrder(['Workspace types', 'Meeting rooms', 'Pages', 'Meeting space guide', 'Articles', 'Running better meetings', 'Customer stories', 'A team that meets weekly', 'Questions', 'Can I book a meeting room by the hour?'])
        ->assertSee('href="/workspaces/meeting-rooms"', false)
        ->assertSee('href="/meeting-space-guide"', false)
        ->assertSee('href="/insights/running-better-meetings"', false)
        ->assertSee('href="/customers/team-meets-weekly"', false)
        ->assertSee('href="/faqs"', false);

    get('/search?q=sector 2')->assertSeeInOrder(['Locations', 'SmartGro A-70'])->assertSee('href="/locations/noida/a-70"', false);
});

it('never finds drafts, locations that are not listed, inactive types or stories without consent', function () {
    Page::factory()->create(['title' => 'Draft rooftop page', 'slug' => 'draft-rooftop']);
    Post::factory()->create(['title' => 'Draft rooftop article', 'slug' => 'draft-rooftop-article']);
    Faq::factory()->create(['question' => 'Draft rooftop question?', 'answer' => 'No.']);
    CaseStudy::factory()->published()->create(['title' => 'Rooftop story without consent', 'consent_on_file' => false]);
    Product::query()->where('key', 'virtual_office')->update(['is_active' => false]);
    $faqForA70 = Faq::factory()->published()->create(['question' => 'Rooftop question about one location?', 'answer' => 'Yes.']);
    if (Faq::hasScopes()) {
        $faqForA70->forceFill(['property_id' => Property::query()->where('code', 'A-70')->value('id')])->save();
    }

    get('/search?q=rooftop')->assertOk()->assertSee('Nothing found for “rooftop”', false)
        ->assertDontSee('Draft rooftop')->assertDontSee('Rooftop story without consent')->assertDontSee('Rooftop question about one location?');
    get('/search?q=virtual')->assertDontSee('Virtual office');
    get('/search?q=A-71')->assertDontSee('SmartGro A-71');
});

it('labels pages with the sample layout as sample content', function () {
    Page::factory()->published()->create(['title' => 'Solutions for startups', 'slug' => 'solutions/startups', 'template' => 'sample']);

    get('/search?q=startups')->assertSeeInOrder(['Solutions for startups', 'Sample content']);
});

it('asks for a query, needs two characters, escapes what was typed and matches wildcards literally', function () {
    Post::factory()->published()->create(['title' => 'Plain article', 'slug' => 'plain-article']);

    get('/search')->assertOk()->assertSee('Type what you are looking for');
    get('/search?q=a')->assertSee('Type at least 2 characters.');
    get('/search?q='.urlencode('<script>x</script>'))->assertDontSee('<script>x</script>', false)->assertSee('&lt;script&gt;x&lt;/script&gt;', false);
    get('/search?q='.urlencode('%%'))->assertSee('Nothing found')->assertDontSee('Plain article');
    get('/search?q[]=array')->assertOk()->assertSee('Type what you are looking for');
});

it('is never indexed and is reached from the search control in the header', function () {
    get('/search?q=desk')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    get('/')->assertSee('href="/search"', false)->assertSee('<span class="sr-only">Search</span>', false);
});
