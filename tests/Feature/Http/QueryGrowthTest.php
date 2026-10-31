<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Post;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
 * Phase 14 (OD-133): the main public pages, the sitemap and the back-office lists and reports must not
 * run one more query per property, workspace, article, question, enquiry or CMS page shown, including
 * CMS pages whose sections load locations, contact details or links (OD-134). Each address is measured
 * with a little content and again with much more; the counts must be equal.
 */
it('runs the same number of queries whatever the number of locations, workspaces, articles, questions and enquiries', function () {
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create());
    $addresses = [
        '/', '/locations', '/locations/noida', '/locations/noida/area/sector-2', '/locations/noida/a-70', '/locations/noida/a-70/private-cabins',
        '/workspaces', '/workspaces/private-cabins', '/workspaces/private-cabins/noida', '/insights', '/faqs', '/customers', '/sitemap.xml',
        '/admin/sales/enquiries', '/admin/sales/tours', '/admin/reports/enquiries-and-tours', '/admin/reports/content-freshness', '/admin/website/search-titles',
    ];
    $queries = function (string $address): int {
        get($address)->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        get($address)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $a70 = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    offerForTest($a70, 'private_office');
    Post::factory()->published()->create();
    Faq::factory()->published()->create();
    Faq::factory()->published()->create(['property_id' => $a70->id]);
    CaseStudy::factory()->published()->create();
    Lead::factory()->forProperty($a70)->count(2)->create();
    $sections = [
        ['type' => 'hero', 'data' => ['eyebrow' => null, 'heading' => 'Heading', 'intro' => 'An introduction for search engines.', 'link' => ['type' => 'page', 'page_id' => null, 'label' => 'More']]],
        ['type' => 'locations', 'data' => ['title' => 'Our locations', 'city_id' => null]],
        ['type' => 'contact', 'data' => ['heading' => null, 'intro' => 'Talk to us.', 'show_locations' => true]],
        ['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Some text.</p>']],
    ];
    Page::factory()->published()->create(['slug' => 'cms-1', 'title' => 'CMS page 1', 'blocks' => $sections]);
    $few = collect($addresses)->mapWithKeys(fn (string $address): array => [$address => $queries($address)]);

    foreach (['A-71', 'H-105'] as $code) {
        $property = publishPropertyProfileForTest(Property::query()->where('code', $code)->sole());
        offerForTest($property, 'private_office');
        offerForTest($property, 'hot_desk');
    }
    offerForTest($a70, 'hot_desk');
    offerForTest($a70, 'meeting_room');
    Post::factory()->published()->count(8)->create();
    Faq::factory()->published()->count(8)->create();
    Faq::factory()->published()->count(4)->create(['property_id' => $a70->id]);
    CaseStudy::factory()->published()->count(4)->create();
    Lead::factory()->forProperty($a70)->count(30)->create();
    foreach (range(2, 8) as $page) {
        Page::factory()->published()->create(['slug' => 'cms-'.$page, 'title' => 'CMS page '.$page, 'blocks' => $sections]);
    }
    $many = collect($addresses)->mapWithKeys(fn (string $address): array => [$address => $queries($address)]);

    expect($many->all())->toBe($few->all());
});
