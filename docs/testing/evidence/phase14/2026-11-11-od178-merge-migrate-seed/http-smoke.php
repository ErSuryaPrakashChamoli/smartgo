<?php

// OD-178: OD-177's read-only smoke test plus the P9-D screens (pipeline stages, deals). OD-177's was OD-169's, plus the Spaces tabs, space pages with their photos (OD-177 e) and the Spaces on the website screen): php http-smoke.php <expected database>
// Boots the app on the expected database with a READ ONLY database session (every write fails), array session
// and cache, then handles public and back-office GET requests in-process (no server, no port 8001). Back-office
// requests run as the database's active Super Admin. Prints each status, and the exception behind any 500.

use App\Models\HomeBanner;
use App\Models\HomeHighlight;
use App\Models\NavigationMenu;
use App\Models\SpaceListing;
use App\Models\User;
use App\Services\Content\NavigationPayload;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$root = '/home/administrator/Documents/website/smartGrow';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

config(['database.connections.mysql.options' => [...config('database.connections.mysql.options', []), PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY']]);
DB::purge('mysql');
$database = DB::selectOne('SELECT DATABASE() AS db, @@session.transaction_read_only AS ro');
if ($database->db !== $argv[1] || (int) $database->ro !== 1 || config('session.driver') !== 'array' || config('cache.default') !== 'array') {
    fwrite(STDERR, "Refusing: database {$database->db}, read only {$database->ro}, session ".config('session.driver').', cache '.config('cache.default')."\n");
    exit(1);
}

$kernel = $app->make(HttpKernel::class);
$result = ['database' => $database->db, 'read_only' => (int) $database->ro, 'public' => [], 'backoffice' => []];
$visit = function (string $path) use ($kernel): array {
    $request = Request::create($path, 'GET');
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $body = (string) $response->getContent();

    return array_filter([
        'status' => $response->getStatusCode(),
        'location' => $response->headers->get('Location'),
        'error' => ($exception = $response->exception ?? null) ? class_basename($exception).': '.mb_substr($exception->getMessage(), 0, 300) : null,
        'robots' => $response->headers->get('X-Robots-Tag'),
        'has' => array_values(array_filter(['Short description', 'Spaces it can be sold with', 'Two-step login is optional', 'Sample content', 'noindex', 'Stock photo, not a SmartGro location', 'srcset="/media/'], fn (string $text): bool => str_contains($body, $text))),
    ], fn ($value): bool => $value !== null && $value !== []);
};

// OD-169: the home page's carousel and highlights strip as rendered, and each live banner's image, as a visitor.
$home = (string) $kernel->handle(Request::create('/', 'GET'))->getContent();
$result['home'] = [
    'banners_available' => HomeBanner::isAvailable(),
    'highlights_available' => HomeHighlight::isAvailable(),
    'carousel' => str_contains($home, 'aria-roledescription="carousel"'),
    'slides' => substr_count($home, 'data-carousel-slide'),
    'single_hero_image' => str_contains($home, '/images/home/coworking-interior'),
    'labels' => ['ai' => substr_count($home, 'AI-generated image, not a photo of a SmartGro location'), 'stock' => substr_count($home, 'Stock photo, not a SmartGro location')],
    'strip' => str_contains($home, 'data-marquee'),
    'example_highlights' => str_contains($home, 'Example highlights'),
    'h1' => preg_match('#<h1 id="home-heading"[^>]*>(.*?)</h1>#s', $home, $match) === 1 ? trim(strip_tags($match[1])) : null,
];
if (HomeBanner::isAvailable()) {
    foreach (HomeBanner::query()->published()->orderBy('sort_order')->pluck('media_asset_id') as $assetId) {
        $result['home']['images']["/media/{$assetId}/w960"] = $visit("/media/{$assetId}/w960")['status'];
    }
}

// OD-177: the Spaces drop-down as rendered (tabs, spaces per tab) and a few space pages, as a visitor.
$result['spaces'] = ['available' => SpaceListing::isAvailable(), 'shown' => SpaceListing::isAvailable() ? SpaceListing::query()->shown()->count() : 0];
if (preg_match('#<nav aria-label="Main" class="hidden lg:block">(.*?)</nav>#s', $home, $nav) === 1) {
    preg_match_all('#data-flyout-tab[^>]*>.*?<span class="flex-1 text-left">(.*?)</span>\s*<span[^>]*>(\d+)</span>#s', $nav[1], $tabs, PREG_SET_ORDER);
    $result['spaces']['tabs'] = array_map(fn (array $tab): string => trim($tab[1]).' ('.$tab[2].')', $tabs);
    $result['spaces']['example_note'] = str_contains($nav[1], 'Example spaces: not yet confirmed by SmartGro.');
}
if (SpaceListing::isAvailable()) {
    foreach (SpaceListing::query()->shown()->orderBy('id')->limit(3)->pluck('slug') as $slug) {
        $result['spaces']['pages']["/spaces/{$slug}"] = $visit("/spaces/{$slug}");
    }
    if (SpaceListing::hasPhotos()) {
        $result['spaces']['with_photo'] = SpaceListing::query()->shown()->whereNotNull('media_asset_id')->count();
        foreach (SpaceListing::query()->shown()->whereNotNull('media_asset_id')->distinct()->orderBy('media_asset_id')->pluck('media_asset_id') as $assetId) {
            $result['spaces']['photos']["/media/{$assetId}/w960"] = $visit("/media/{$assetId}/w960")['status'];
        }
    }
}
$result['spaces']['pages']['/spaces/no-such-space'] = $visit('/spaces/no-such-space');

foreach (['/', '/workspaces', '/locations', '/insights', '/faqs', '/search', '/enquire', '/book-a-tour', '/sitemap.xml', '/robots.txt', '/find-a-workspace', '/admin/login'] as $path) {
    $result['public'][$path] = $visit($path);
}

$owner = User::query()->where('is_active', true)->whereHas('roles', fn ($roles) => $roles->where('name', 'super_admin'))->orderBy('id')->firstOrFail();
auth()->guard('web')->setUser($owner);
$result['acting_as'] = $owner->id;
$menuId = NavigationMenu::query()->where('handle', 'main')->value('id');
foreach ([
    "/admin/website/navigation-menus/{$menuId}/edit", '/admin/properties/workspace-categories/1/edit',
    ...(HomeBanner::isAvailable() ? ['/admin/website/home-banners', '/admin/website/home-banners/'.HomeBanner::query()->min('id').'/edit'] : []),
    ...(HomeHighlight::isAvailable() ? ['/admin/website/home-highlights', '/admin/website/home-highlights/'.HomeHighlight::query()->min('id').'/edit'] : []),
    ...(SpaceListing::isAvailable() ? ['/admin/website/space-listings', '/admin/website/space-listings/create', '/admin/website/space-listings/'.SpaceListing::query()->min('id').'/edit'] : []),
    '/admin', '/admin/properties', '/admin/properties/cities', '/admin/properties/inventory-imports', '/admin/properties/inventory-imports/upload', '/admin/properties/offers', '/admin/properties/price-plans', '/admin/properties/properties', '/admin/properties/spaces', '/admin/properties/space-types', '/admin/properties/website-profiles', '/admin/properties/workspace-categories', '/admin/properties/workspace-offerings', '/admin/reports', '/admin/reports/content-freshness', '/admin/reports/enquiries-and-tours', '/admin/sales', '/admin/sales/assignment-rules', '/admin/sales/assignment-rules/create', '/admin/sales/pipeline-stages', '/admin/sales/pipeline-stages/create', '/admin/sales/deals', '/admin/sales/closing-reasons', '/admin/sales/enquiries', '/admin/sales/tours', '/admin/settings', '/admin/settings/activities', '/admin/settings/security', '/admin/settings/users', '/admin/website', '/admin/website/customer-stories', '/admin/website/faqs', '/admin/website/insights', '/admin/website/insight-topics', '/admin/website/media-assets', '/admin/website/missing-pages', '/admin/website/navigation-menus', '/admin/website/pages', '/admin/website/redirects', '/admin/website/search-titles', '/admin/website/settings', '/admin/website/testimonials', '/admin/website/twin-models', '/admin/design-previews', '/admin/design-previews/permissions-review',
] as $path) {
    $result['backoffice'][$path] = $visit($path);
}

// The menu editor's rules accept a description and an icon on this database (validation only, nothing saved).
$menu = NavigationMenu::query()->where('handle', 'main')->sole();
$payload = ['items' => [['label' => 'Solutions', 'type' => 'group', 'children' => [['label' => 'Workspaces', 'type' => 'group', 'description' => 'Desks and offices.', 'icon' => 'building-office', 'children' => [
    ['label' => 'All workspace types', 'type' => 'section', 'section' => 'workspace_types', 'description' => 'Every type of workspace.', 'icon' => 'squares-2x2'],
]]]]]];
$validated = app(NavigationPayload::class)->validate($menu, $payload, forSubmission: true);
$result['menu_rules_keep_details'] = ($validated['items'][0]['children'][0]['children'][0]['description'] ?? null) === 'Every type of workspace.' && ($validated['items'][0]['children'][0]['icon'] ?? null) === 'building-office';
$result['p9d'] = ['deal_tables' => \App\Models\Deal::isAvailable(), 'pipeline_permission' => $owner->can('leads.pipeline.manage')];
$result['still_read_only'] = (int) DB::selectOne('SELECT @@session.transaction_read_only AS ro')->ro;

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
