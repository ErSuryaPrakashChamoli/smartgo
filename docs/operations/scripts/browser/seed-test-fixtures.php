<?php

// SmartGro browser checks: labelled fixtures on the TEST database, created through the real actions.
//
//   DB_DATABASE=smartgro_testing php docs/operations/scripts/browser/seed-test-fixtures.php <out-dir> [privacy|navigation-groups]
//
// Refuses to run unless the connection reaches `smartgro_testing`. Rebuilds that database (every test
// run does the same), then adds content labelled "TEST FIXTURE": three listed properties with offerings,
// one location's getting-here details and own numbers (fixture numbers, not real ones), SmartGro's main
// contact details, menus, a contact page, an about page, a page with every text-only section, a page
// with an image and a testimonial section, a legal page, an insight, a customer story, FAQs (general, A-70's, hot desks'), a
// coming-soon city, a 410 rule, an insight topic, an enquiry, a draft page for the staff preview, two
// test-only assignment rules (switched off) with one decision, one
// floor of A-70 with cabins, a pool and a hybrid zone, some allocated, and four test-only 3D models
// (Tests\Support\Twin\TestGlb, uploaded through the real checks). The models' files are stored only when
// LARAVEL_STORAGE_PATH points at a temporary folder (run-checks.sh sets it), never in the shared storage.
// It writes the rendered 403, 419, 429, 500 and 503 pages into <out-dir> and prints the fixture IDs and
// staff session cookies (for this database only) as JSON. With "privacy" it only adds a fixture privacy
// notice to an existing fixture set, which opens the enquiry form on this database. With "navigation-groups"
// it only publishes a main menu with two drop-downs, one with sub-groups, over an existing fixture set
// (verify-csp.mjs; OD-158), since the fixture menu has none. Nothing here is real SmartGro content.
use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Crm\AssignNewLead;
use App\Actions\Crm\SaveAssignmentRule;
use App\Actions\Crm\SetAssignmentRuleActive;
use App\Actions\Inventory\AddSpaces;
use App\Actions\Inventory\RecordAllocation;
use App\Actions\Inventory\SaveFloor;
use App\Actions\Inventory\SaveSpace;
use App\Actions\Media\CreateMediaAsset;
use App\Actions\Properties\SaveProperty;
use App\Actions\Twin\CreateTwinModel;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RedirectMatch;
use App\Enums\Twin\CaptureType;
use App\Http\Controllers\Backoffice\RevisionPreviewController;
use App\Models\AssignmentRule;
use App\Models\CaseStudy;
use App\Models\City;
use App\Models\Contact;
use App\Models\Faq;
use App\Models\Lead;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostTopic;
use App\Models\Product;
use App\Models\Property;
use App\Models\Redirect;
use App\Models\SiteSetting;
use App\Models\SpaceType;
use App\Models\Testimonial;
use App\Models\TourAvailabilityRule;
use App\Models\TourRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Support\Twin\TestGlb;

$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::selectOne('SELECT DATABASE() AS db')->db !== 'smartgro_testing' || $app->configurationIsCached()) {
    fwrite(STDERR, "Refusing: not the testing database, or the configuration is cached\n");
    exit(1);
}

$out = $argv[1] ?? null;

if ($out === null || ! is_dir($out)) {
    fwrite(STDERR, "Usage: seed-test-fixtures.php <existing-out-dir> [privacy|navigation-groups]\n");
    exit(1);
}

$people = ['admin' => StaffRole::SuperAdmin, 'marketing' => StaffRole::MarketingManager, 'marketing2' => StaffRole::MarketingManager, 'operations' => StaffRole::OperationsManager, 'sales' => StaffRole::SalesCrmExecutive];

if (in_array('privacy', $argv, true) || in_array('navigation-groups', $argv, true)) {
    $staff = array_map(fn (string $key): User => User::query()->where('email', "{$key}@fixture.test")->sole(), array_combine(array_keys($people), array_keys($people)));
} else {
    Artisan::call('migrate:fresh', ['--force' => true]);
    Artisan::call('db:seed', ['--class' => ReferenceDataSeeder::class, '--force' => true]);
    app(SyncAccessControl::class)->handle();

    $staff = [];
    foreach ($people as $key => $role) {
        $staff[$key] = User::query()->forceCreate(['name' => ucfirst($key).' (fixture)', 'email' => "{$key}@fixture.test", 'password' => bin2hex(random_bytes(16)), 'is_active' => true, 'has_email_authentication' => true]);
        $staff[$key]->syncRoles([$role]);
    }
}

$publish = function ($content, array $payload) use ($staff): void {
    $draft = app(CreateDraft::class)->handle($content, $staff['marketing'], $payload);
    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $staff['marketing']), $staff['marketing2']), $staff['admin']);
};

if (in_array('privacy', $argv, true)) {
    $publish(Page::query()->forceCreate(['status' => 'draft']), ['title' => 'TEST FIXTURE privacy notice', 'slug' => 'legal/privacy', 'template' => 'legal', 'legal_review_reference' => 'TEST-FIXTURE',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Test fixture for the browser checks. Not legal text.</p>']]]]);
    echo json_encode(['privacy' => 'published']), PHP_EOL;
    exit(0);
}

if (in_array('navigation-groups', $argv, true)) {
    $page = fn (string $label, string $slug): array => ['label' => $label, 'type' => 'page', 'page_id' => Page::query()->where('slug', $slug)->sole()->id];
    $publish(NavigationMenu::query()->where('handle', 'main')->sole(), ['items' => [
        ['label' => 'Spaces', 'type' => 'section', 'section' => 'workspace_types'],
        ['label' => 'TEST FIXTURE group', 'type' => 'group', 'children' => [$page('TEST FIXTURE solutions link', 'solutions'), $page('TEST FIXTURE community link', 'community')]],
        ['label' => 'TEST FIXTURE group with sub-groups', 'type' => 'group', 'children' => [
            ['label' => 'TEST FIXTURE sub-group one', 'type' => 'group', 'children' => [$page('TEST FIXTURE about link', 'about')]],
            ['label' => 'TEST FIXTURE sub-group two', 'type' => 'group', 'children' => [$page('TEST FIXTURE contact link', 'contact')]],
        ]],
        ['label' => 'Resources', 'type' => 'section', 'section' => 'insights'],
    ]]);
    echo json_encode(['navigation' => 'published with groups']), PHP_EOL;
    exit(0);
}

$record = function (Property $property, array $keys, array $building = []) use ($staff): Property {
    app(SaveProperty::class)->handle($staff['operations'], $property, [
        ...$property->only(['name', 'code', 'slug', 'city_id', 'micro_market_id']),
        'operating_model' => $property->operating_model->value, 'operational_status' => $property->operational_status->value,
        'building' => [...$property->primaryBuilding->only(['address_line_1', 'locality']), ...$building],
        'operating_hours' => ['mon' => ['opens' => '09:00', 'closes' => '19:00'], 'tue' => ['opens' => '09:00', 'closes' => '19:00']],
        'product_ids' => Product::query()->whereIn('key', $keys)->pluck('id')->all(),
    ]);

    return $property->fresh();
};

$a70 = $record(Property::query()->where('code', 'A-70')->sole(), ['private_office', 'hot_desk', 'meeting_room'], ['latitude' => '28.000001', 'longitude' => '77.000001']);
$a71 = $record(Property::query()->where('code', 'A-71')->sole(), ['dedicated_desk']);
$h105 = $record(Property::query()->where('code', 'H-105')->sole(), ['hot_desk', 'meeting_room']);

$publish($a70->profile, [
    'headline' => 'TEST FIXTURE headline for A-70', 'summary' => 'TEST FIXTURE summary. Not SmartGro copy.',
    'getting_there' => 'TEST FIXTURE directions.', 'nearest_metro' => 'TEST FIXTURE station', 'metro_walk_minutes' => 6,
    'buses' => 'TEST FIXTURE bus stop', 'parking' => 'TEST FIXTURE parking', 'accessibility' => 'TEST FIXTURE accessibility note',
    'phone' => '+91 0000 000 000', 'whatsapp' => '+910000000000',
]);
$publish($a71->profile, ['headline' => 'TEST FIXTURE headline for A-71', 'summary' => 'TEST FIXTURE summary. Not SmartGro copy.']);
$publish($h105->profile, ['headline' => 'TEST FIXTURE headline for H-105', 'summary' => 'TEST FIXTURE summary. Not SmartGro copy.', 'nearest_metro' => 'TEST FIXTURE station two', 'parking' => 'TEST FIXTURE parking two']);

foreach ([$a70, $a71, $h105] as $property) {
    foreach ($property->fresh()->offerings as $offering) {
        $publish($offering->profile, ['headline' => 'TEST FIXTURE '.$offering->product->name, 'description' => 'TEST FIXTURE description.', 'included' => ['Fixture item'], 'not_included' => ['Fixture exclusion']]);
    }
}

// Inventory (Phase 5, OD-127): one floor of A-70 with three cabins, a hot-desk pool and a hybrid desk zone,
// some allocated, all labelled TEST FIXTURE. A-71 keeps no floor, for the location set-up journey.
$inventoryFloor = app(SaveFloor::class)->handle($staff['operations'], $a70, null, ['building_id' => $a70->primaryBuilding->id, 'building_level' => 1, 'label' => 'TEST FIXTURE floor', 'reason' => 'TEST FIXTURE']);
$spaceType = fn (string $key): int => SpaceType::query()->where('key', $key)->value('id');
$offeringOf = fn (string $key): int => $a70->offerings()->whereHas('product', fn ($product) => $product->where('key', $key))->value('id');
$fixtureCabins = app(AddSpaces::class)->handle($staff['operations'], $a70, [
    'floor_id' => $inventoryFloor->id, 'space_type_id' => $spaceType('private_cabin'), 'inventory_mode' => 'exclusive', 'count' => 3, 'code_prefix' => 'TF-C',
    'first_number' => 1, 'name_prefix' => 'TEST FIXTURE cabin', 'capacity_seats' => 4, 'offering_ids' => [$offeringOf('private_office')], 'reason' => 'TEST FIXTURE',
]);
$fixturePool = app(SaveSpace::class)->handle($staff['operations'], $a70, null, [
    'floor_id' => $inventoryFloor->id, 'space_type_id' => $spaceType('desk_zone_flexible'), 'inventory_mode' => 'pooled', 'code' => 'TF-P1', 'name' => 'TEST FIXTURE pool',
    'capacity_seats' => 12, 'offering_ids' => [$offeringOf('hot_desk')], 'reason' => 'TEST FIXTURE',
]);
$fixtureZone = app(SaveSpace::class)->handle($staff['operations'], $a70, null, [
    'floor_id' => $inventoryFloor->id, 'space_type_id' => $spaceType('desk_zone_dedicated'), 'inventory_mode' => 'hybrid', 'code' => 'TF-H1', 'name' => 'TEST FIXTURE hybrid zone',
    'capacity_seats' => 6, 'offering_ids' => [], 'reason' => 'TEST FIXTURE',
]);
$allocate = fn ($space, array $data) => app(RecordAllocation::class)->handle($staff['operations'], $space, [...$data, 'reason' => 'TEST FIXTURE']);
$allocate($fixtureCabins->first(), ['agreement_reference' => 'TF-AG-1', 'starts_on' => now()->subMonth()->toDateString(), 'ends_on' => now()->addMonths(2)->toDateString()]);
$allocate($fixturePool, ['agreement_reference' => 'TF-AG-2', 'seats_allocated' => 5, 'starts_on' => now()->toDateString()]);
$allocate($fixtureZone, ['agreement_reference' => 'TF-AG-3', 'space_seat_id' => $fixtureZone->seats->first()->id, 'starts_on' => now()->toDateString()]);
$allocate($fixtureZone, ['agreement_reference' => 'TF-AG-4', 'seats_allocated' => 2, 'starts_on' => now()->toDateString()]);

$publish(SiteSetting::query()->where('handle', 'contact')->sole(), ['phone' => '+91 0000 000 001', 'whatsapp' => '+910000000001', 'email' => 'hello@fixture.test']);

$contactPage = Page::query()->forceCreate(['status' => 'draft']);
$publish($contactPage, ['title' => 'Contact', 'slug' => 'contact', 'template' => 'standard', 'blocks' => [
    ['type' => 'contact', 'data' => ['heading' => 'TEST FIXTURE contact heading', 'intro' => 'TEST FIXTURE introduction.', 'show_locations' => true]],
]]);
$about = Page::query()->forceCreate(['status' => 'draft']);
$publish($about, ['title' => 'TEST FIXTURE about page', 'slug' => 'about', 'template' => 'standard', 'blocks' => [
    ['type' => 'text', 'data' => ['heading' => 'TEST FIXTURE heading', 'body' => '<p>TEST FIXTURE text. Not SmartGro copy.</p>']],
]]);
// A second version of the about page waiting for approval, for the approver's "What changed" (UJ-8, OD-126).
app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($about->fresh(), $staff['marketing'], ['title' => 'TEST FIXTURE about page, revised', 'slug' => 'about', 'template' => 'standard', 'blocks' => [
    ['type' => 'text', 'data' => ['heading' => 'TEST FIXTURE heading', 'body' => '<p>TEST FIXTURE text, revised. Not SmartGro copy.</p>']],
]]), $staff['marketing']);
$publish(Page::query()->forceCreate(['status' => 'draft']), ['title' => 'TEST FIXTURE sections', 'slug' => 'test-fixture-sections', 'template' => 'standard', 'blocks' => [
    ['type' => 'hero', 'data' => ['eyebrow' => 'TEST FIXTURE', 'heading' => 'TEST FIXTURE hero heading', 'intro' => 'TEST FIXTURE introduction.', 'link' => ['label' => 'TEST FIXTURE link', 'type' => 'page', 'page_id' => $about->id, 'url' => null]]],
    ['type' => 'text', 'data' => ['heading' => 'TEST FIXTURE text', 'body' => '<p>TEST FIXTURE <strong>text</strong>.</p>']],
    ['type' => 'features', 'data' => ['heading' => 'TEST FIXTURE features', 'intro' => null, 'items' => [['title' => 'Fixture one', 'text' => 'Fixture text.'], ['title' => 'Fixture two', 'text' => 'Fixture text.']]]],
    ['type' => 'stats', 'data' => ['heading' => 'TEST FIXTURE figures', 'items' => [['value' => '1', 'label' => 'fixture figure'], ['value' => '2', 'label' => 'fixture figure']], 'source' => 'TEST FIXTURE source']],
    ['type' => 'comparison', 'data' => ['heading' => 'TEST FIXTURE comparison', 'intro' => null, 'column_1' => 'Option A', 'column_2' => 'Option B', 'column_3' => null, 'column_4' => null, 'rows' => [['label' => 'Fixture row', 'cell_1' => 'A', 'cell_2' => 'B', 'cell_3' => null, 'cell_4' => null]]]],
    ['type' => 'faq', 'data' => ['heading' => null, 'items' => [['question' => 'TEST FIXTURE question?', 'answer' => 'TEST FIXTURE answer.']]]],
    ['type' => 'cta', 'data' => ['eyebrow' => null, 'heading' => 'TEST FIXTURE call to action', 'text' => 'Fixture.', 'primary_link' => ['label' => 'Fixture button', 'type' => 'page', 'page_id' => $about->id, 'url' => null], 'secondary_link' => null]],
]]);
$publish(Page::query()->forceCreate(['status' => 'draft']), ['title' => 'TEST FIXTURE terms', 'slug' => 'legal/terms', 'template' => 'legal', 'legal_review_reference' => 'TEST-FIXTURE',
    'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>TEST FIXTURE. Not legal text.</p>']]]]);

// The owner's header navigation (OD-140): Spaces, Locations, Solutions, Community, About, Resources, and
// Book a Tour as the main button. SmartGro has no Solutions or Community page yet, so the fixture
// pages below stand in for them, labelled, on this database only.
$solutions = Page::query()->forceCreate(['status' => 'draft']);
$publish($solutions, ['title' => 'TEST FIXTURE solutions page', 'slug' => 'solutions', 'template' => 'standard', 'blocks' => [
    ['type' => 'text', 'data' => ['heading' => 'TEST FIXTURE heading', 'body' => '<p>TEST FIXTURE text. Not SmartGro copy.</p>']],
]]);
$community = Page::query()->forceCreate(['status' => 'draft']);
$publish($community, ['title' => 'TEST FIXTURE community page', 'slug' => 'community', 'template' => 'standard', 'blocks' => [
    ['type' => 'text', 'data' => ['heading' => 'TEST FIXTURE heading', 'body' => '<p>TEST FIXTURE text. Not SmartGro copy.</p>']],
]]);

$publish(NavigationMenu::query()->where('handle', 'main')->sole(), ['items' => [
    ['label' => 'Spaces', 'type' => 'section', 'section' => 'workspace_types'],
    ['label' => 'Locations', 'type' => 'section', 'section' => 'locations'],
    ['label' => 'Solutions', 'type' => 'page', 'page_id' => $solutions->id],
    ['label' => 'Community', 'type' => 'page', 'page_id' => $community->id],
    ['label' => 'About', 'type' => 'page', 'page_id' => $about->id],
    ['label' => 'Resources', 'type' => 'section', 'section' => 'insights'],
]]);
$publish(NavigationMenu::query()->where('handle', 'actions')->sole(), ['items' => [
    ['label' => 'Book a Tour', 'type' => 'section', 'section' => 'book_a_tour'],
]]);

$topic = PostTopic::factory()->create(['name' => 'TEST FIXTURE topic', 'slug' => 'test-fixture-topic']);
Post::factory()->published()->create(['title' => 'TEST FIXTURE insight', 'slug' => 'test-fixture-insight', 'excerpt' => 'TEST FIXTURE excerpt.', 'topic_ids' => [$topic->id]]);
CaseStudy::factory()->published()->create(['title' => 'TEST FIXTURE customer story', 'slug' => 'test-fixture-customer-story']);
Faq::factory()->published()->create(['question' => 'TEST FIXTURE question?', 'answer' => 'TEST FIXTURE answer.']);
// FAQs shown on a location's page and on a workspace type's page (FR-CMS-7, OD-125).
Faq::factory()->published()->create(['question' => 'TEST FIXTURE question about A-70?', 'answer' => 'TEST FIXTURE answer about A-70.', 'property_id' => $a70->id]);
Faq::factory()->published()->create(['question' => 'TEST FIXTURE question about hot desks?', 'answer' => 'TEST FIXTURE answer about hot desks.', 'product_id' => Product::query()->where('key', 'hot_desk')->sole()->id]);
City::query()->where('slug', 'gurugram')->update(['is_publicly_visible' => true]);
Redirect::factory()->create(['from_path' => '/pin/', 'match_type' => RedirectMatch::Prefix, 'to_path' => null, 'status_code' => 410]);

// An enquiry as the form leaves it, with no owner, company, phone or team size, so the back-office screens
// show their text for empty values ("Unassigned", "—").
$lead = Lead::factory()->forProperty($a70)
    ->for(Contact::factory()->state(['name' => 'TEST FIXTURE visitor', 'email' => 'visitor@fixture.test', 'phone_e164' => null, 'company_name' => null]), 'contact')
    ->create(['submitted_name' => 'TEST FIXTURE visitor', 'submitted_email' => 'visitor@fixture.test', 'message' => 'TEST FIXTURE enquiry (not a real visitor).']);

// Tour hours for A-70 on every day (TEST FIXTURE values, not SmartGro's tour rules, which are open: D-28)
// and a visitor's request waiting for confirmation tomorrow, for the tour screens and journey (OD-129).
foreach (range(1, 7) as $weekday) {
    TourAvailabilityRule::query()->forceCreate(['property_id' => $a70->id, 'weekday' => $weekday, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'slot_minutes' => 30, 'max_concurrent_tours' => 2, 'created_by' => $staff['operations']->id]);
}
$tour = TourRequest::factory()->create(['lead_id' => $lead->id, 'property_id' => $a70->id, 'scheduled_start_at' => CarbonImmutable::now('Asia/Kolkata')->addDay()->setTime(11, 0)->utc(), 'notes' => 'TEST FIXTURE tour request (not a real visitor).']);

if (realpath(storage_path()) === realpath($root.'/storage')) {
    fwrite(STDERR, "Refusing to store the test 3D model in the shared storage folder: set LARAVEL_STORAGE_PATH to a temporary folder\n");
    exit(1);
}

// Test-only 3D models (none shows a real location): the small Meshopt and Draco models, and two larger
// ones for the performance checks, one typical in size and one at the limits (500,000 triangles, 250
// pieces drawn, 33,554,432 texture pixels, just under 15 MB). Each goes through the real upload and
// its checks, which are timed.
$twinModels = [];
$twinChecks = [];
foreach ([
    'twin_model' => ['TEST FIXTURE model (not a real location)', fn (): string => TestGlb::model()],
    'twin_model_draco' => ['TEST FIXTURE Draco model (not a real location)', fn (): string => TestGlb::dracoModel()],
    'twin_model_typical' => ['TEST FIXTURE typical-size model (not a real location)', fn (): string => TestGlb::panelsModel(50, 4, 1024, 'typical size')],
    'twin_model_limits' => ['TEST FIXTURE model at the limits (not a real location)', fn (): string => TestGlb::panelsModel(250, 8, 2048, 'at the limits')],
] as $key => [$title, $bytes]) {
    $glb = $bytes();
    $file = UploadedFile::fake()->createWithContent("{$key}.glb", $glb);

    // The model at the limits, kept for an upload through the back-office form (verify-admin.mjs).
    if ($key === 'twin_model_limits') {
        file_put_contents("{$out}/upload-at-limits.glb", $glb);
    }

    $started = hrtime(true);
    $twinModels[$key] = app(CreateTwinModel::class)->handle($staff['marketing'], $file, [
        'property_id' => $a70->id,
        'title' => $title,
        'capture_type' => CaptureType::ConceptualModel->value,
        'captured_by' => 'TEST FIXTURE',
    ]);
    $twinChecks[$key] = ['upload_ms' => round((hrtime(true) - $started) / 1e6, 1), 'bytes' => $twinModels[$key]->file_bytes, 'triangles' => $twinModels[$key]->triangle_count];
}
$twinModel = $twinModels['twin_model'];
file_put_contents("{$out}/upload-oversized.glb", TestGlb::modelOfSize(15 * 1024 * 1024 + 4));

// The image and testimonial sections (Phase 6 pack §11.4: never checked, for want of real media and
// consented testimonials): a generated image and a testimonial, both labelled, on a page of their own.
$image = app(CreateMediaAsset::class)->handle($staff['marketing'], UploadedFile::fake()->image('test-fixture.jpg', 1600, 900), [
    'title' => 'TEST FIXTURE image', 'origin' => 'commissioned', 'alt_text' => 'TEST FIXTURE image (plain black, not a real photo)', 'credit_line' => 'TEST FIXTURE credit',
    'not_from_old_website' => true,
]);
$testimonial = Testimonial::factory()->published()->create([
    'quote' => 'TEST FIXTURE quote. Not a real customer.', 'person_name' => 'TEST FIXTURE person', 'person_title' => 'Fixture title', 'company_name' => 'TEST FIXTURE company', 'consent_reference' => 'TEST-FIXTURE',
]);
$publish(Page::query()->forceCreate(['status' => 'draft']), ['title' => 'TEST FIXTURE media sections', 'slug' => 'test-fixture-media', 'template' => 'standard', 'blocks' => [
    ['type' => 'text', 'data' => ['heading' => 'TEST FIXTURE media sections', 'body' => '<p>TEST FIXTURE. Not SmartGro copy.</p>']],
    ['type' => 'image', 'data' => ['media_asset_id' => $image->id, 'alt' => 'TEST FIXTURE image (plain black, not a real photo)', 'decorative' => false, 'caption' => 'TEST FIXTURE caption']],
    ['type' => 'testimonial', 'data' => ['testimonial_id' => $testimonial->id]],
]]);

// A draft that is never published, for the staff preview (Phase 6 pack §11.4: staff previews were never
// checked). The preview's signed address lasts 30 minutes, longer than the checks take.
$previewDraft = app(CreateDraft::class)->handle(Page::query()->forceCreate(['status' => 'draft']), $staff['marketing'], ['title' => 'TEST FIXTURE draft', 'slug' => 'test-fixture-draft', 'template' => 'standard', 'blocks' => [
    ['type' => 'hero', 'data' => ['eyebrow' => 'TEST FIXTURE', 'heading' => 'TEST FIXTURE draft heading', 'intro' => 'TEST FIXTURE draft, never published.', 'link' => null]],
    ['type' => 'image', 'data' => ['media_asset_id' => $image->id, 'alt' => 'TEST FIXTURE image (plain black, not a real photo)', 'decorative' => false, 'caption' => null]],
    ['type' => 'testimonial', 'data' => ['testimonial_id' => $testimonial->id]],
]]);

(new RegisterErrorViewPaths)();
foreach ([403, 419, 429, 500, 503] as $status) {
    file_put_contents("{$out}/error-{$status}.html", view("errors::{$status}")->render());
}

// Two test-only assignment rules (P9-B browser checks): never real rules, test database only, both left
// switched off. The first is switched on just long enough to give one test-only enquiry an owner, so its
// page has a decision to list, then off again so its form can be opened; the second has nobody, so
// switching it on is refused. Enquiries the kit sends later are not assigned by either.
$assignmentRule = app(SaveAssignmentRule::class)->handle($staff['admin'], new AssignmentRule, [
    'name' => 'TEST FIXTURE rule (not a real rule)', 'priority' => 1, 'strategy' => 'round_robin',
    'criteria' => ['property_ids' => [$a70->id], 'team_sizes' => ['2-5']], 'assignee_ids' => [$staff['admin']->id],
]);
app(SetAssignmentRuleActive::class)->handle($staff['admin'], $assignmentRule, true);
$assignedLead = Lead::factory()->forProperty($a70)
    ->for(Contact::factory()->state(['name' => 'TEST FIXTURE assigned visitor', 'email' => 'assigned@fixture.test', 'phone_e164' => null, 'company_name' => null]), 'contact')
    ->create(['submitted_name' => 'TEST FIXTURE assigned visitor', 'submitted_email' => 'assigned@fixture.test', 'team_size' => '2-5', 'message' => 'TEST FIXTURE enquiry for the assignment rule checks.']);
app(AssignNewLead::class)->handle($assignedLead);
app(SetAssignmentRuleActive::class)->handle($staff['admin'], $assignmentRule, false);
$emptyRule = app(SaveAssignmentRule::class)->handle($staff['admin'], new AssignmentRule, [
    'name' => 'TEST FIXTURE rule without people', 'priority' => 2, 'strategy' => 'fixed_user', 'criteria' => [], 'assignee_ids' => [],
]);

// Staff sessions for this database only: one session row per person, as signing in leaves it.
$sessions = [];
foreach (['admin', 'marketing', 'operations', 'sales'] as $key) {
    app('session')->forgetDrivers();
    $store = app('session')->driver();
    $store->start();
    $store->put('login_web_'.sha1(SessionGuard::class), $staff[$key]->getKey());
    $store->save();
    $cookie = config('session.cookie');
    $sessions[$key] = $cookie."\t".Crypt::encrypt(CookieValuePrefix::create($cookie, Crypt::getKey()).$store->getId(), false);
}

echo json_encode([
    'ids' => ['a70' => $a70->id, 'a71' => $a71->id, 'h105' => $h105->id, 'a70_profile' => $a70->profile->id, 'noida' => $a70->city_id, 'contact_page' => $contactPage->id, 'about_page' => $about->id, 'lead' => $lead->id, 'assignment_rule' => $assignmentRule->id, 'assignment_rule_empty' => $emptyRule->id, 'assigned_lead' => $assignedLead->id, 'space_cabin' => $fixtureCabins->first()->id, 'space_zone' => $fixtureZone->id, 'tour' => $tour->id, ...array_map(fn ($model): int => $model->id, $twinModels)],
    'twin_checks' => $twinChecks,
    'upload_files' => ['at_limits' => "{$out}/upload-at-limits.glb", 'oversized' => "{$out}/upload-oversized.glb"],
    'preview' => (string) parse_url(RevisionPreviewController::url($previewDraft), PHP_URL_PATH).'?'.parse_url(RevisionPreviewController::url($previewDraft), PHP_URL_QUERY),
    'sessions' => $sessions,
]), PHP_EOL;
