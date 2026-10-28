<?php

// SmartGro browser checks: labelled fixtures on the TEST database, created through the real actions.
//
//   DB_DATABASE=smartgro_testing php docs/operations/scripts/browser/seed-test-fixtures.php <out-dir> [privacy]
//
// Refuses to run unless the connection reaches `smartgro_testing`. Rebuilds that database (every test
// run does the same), then adds content labelled "TEST FIXTURE": three listed properties with offerings,
// one location's getting-here details and own numbers (fixture numbers, not real ones), SmartGro's main
// contact details, menus, a contact page, an about page, a page with every text-only section, a legal
// page, an insight, a customer story, an FAQ, a coming-soon city, a 410 rule and four test-only 3D models
// (Tests\Support\Twin\TestGlb, uploaded through the real checks). The models' files are stored only when
// LARAVEL_STORAGE_PATH points at a temporary folder (run-checks.sh sets it), never in the shared storage.
// It writes the rendered 403, 419, 429, 500 and 503 pages into <out-dir> and prints the fixture IDs and
// staff session cookies (for this database only) as JSON. With "privacy" it only adds a fixture privacy
// notice to an existing fixture set, which opens the enquiry form on this database. Nothing here is real
// SmartGro content.
use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Properties\SaveProperty;
use App\Actions\Twin\CreateTwinModel;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RedirectMatch;
use App\Enums\Twin\CaptureType;
use App\Models\CaseStudy;
use App\Models\City;
use App\Models\Faq;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Property;
use App\Models\Redirect;
use App\Models\SiteSetting;
use App\Models\User;
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
    fwrite(STDERR, "Usage: seed-test-fixtures.php <existing-out-dir> [privacy]\n");
    exit(1);
}

$people = ['admin' => StaffRole::SuperAdmin, 'marketing' => StaffRole::MarketingManager, 'marketing2' => StaffRole::MarketingManager, 'operations' => StaffRole::OperationsManager, 'sales' => StaffRole::SalesCrmExecutive];

if (in_array('privacy', $argv, true)) {
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

$publish(SiteSetting::query()->where('handle', 'contact')->sole(), ['phone' => '+91 0000 000 001', 'whatsapp' => '+910000000001', 'email' => 'hello@fixture.test']);

$contactPage = Page::query()->forceCreate(['status' => 'draft']);
$publish($contactPage, ['title' => 'Contact', 'slug' => 'contact', 'template' => 'standard', 'blocks' => [
    ['type' => 'contact', 'data' => ['heading' => 'TEST FIXTURE contact heading', 'intro' => 'TEST FIXTURE introduction.', 'show_locations' => true]],
]]);
$about = Page::query()->forceCreate(['status' => 'draft']);
$publish($about, ['title' => 'TEST FIXTURE about page', 'slug' => 'about', 'template' => 'standard', 'blocks' => [
    ['type' => 'text', 'data' => ['heading' => 'TEST FIXTURE heading', 'body' => '<p>TEST FIXTURE text. Not SmartGro copy.</p>']],
]]);
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

$publish(NavigationMenu::query()->where('handle', 'main')->sole(), ['items' => [
    ['label' => 'Workspaces', 'type' => 'section', 'section' => 'workspace_types'],
    ['label' => 'Locations', 'type' => 'section', 'section' => 'locations'],
    ['label' => 'About', 'type' => 'page', 'page_id' => $about->id],
    ['label' => 'Contact', 'type' => 'page', 'page_id' => $contactPage->id],
]]);
$publish(NavigationMenu::query()->where('handle', 'actions')->sole(), ['items' => [['label' => 'Enquire', 'type' => 'section', 'section' => 'enquire']]]);

Post::factory()->published()->create(['title' => 'TEST FIXTURE insight', 'slug' => 'test-fixture-insight', 'excerpt' => 'TEST FIXTURE excerpt.']);
CaseStudy::factory()->published()->create(['title' => 'TEST FIXTURE customer story', 'slug' => 'test-fixture-customer-story']);
Faq::factory()->published()->create(['question' => 'TEST FIXTURE question?', 'answer' => 'TEST FIXTURE answer.']);
City::query()->where('slug', 'gurugram')->update(['is_publicly_visible' => true]);
Redirect::factory()->create(['from_path' => '/pin/', 'match_type' => RedirectMatch::Prefix, 'to_path' => null, 'status_code' => 410]);

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
    $file = UploadedFile::fake()->createWithContent("{$key}.glb", $bytes());
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

(new RegisterErrorViewPaths)();
foreach ([403, 419, 429, 500, 503] as $status) {
    file_put_contents("{$out}/error-{$status}.html", view("errors::{$status}")->render());
}

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
    'ids' => ['a70' => $a70->id, 'a71' => $a71->id, 'h105' => $h105->id, 'a70_profile' => $a70->profile->id, 'noida' => $a70->city_id, 'contact_page' => $contactPage->id, ...array_map(fn ($model): int => $model->id, $twinModels)],
    'twin_checks' => $twinChecks,
    'sessions' => $sessions,
]), PHP_EOL;
