<?php
// OD-176 read-only smoke test of the demo content: php demo-smoke.php <expected database>
// Boots the app on the expected database with a READ ONLY database session (every write fails), array session and
// cache, and handles the public pages the demo content fills in-process (no server, no port 8000/8001). For each page
// it records the status and whether the words that mark it as an example (or the closed-form notices) are present.

use App\Models\MediaAsset;
use App\Models\Offering;
use App\Models\Property;
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
$marks = [
    'stock_label' => 'Stock photo, not a SmartGro location',
    'example_text' => 'Example text',
    'sample_notice' => 'Sample content',
    'placeholder_phone' => '+91 00000 00000',
    'placeholder_email' => 'hello@smartgro.example',
    'contact_on_their_way' => 'Contact details are on their way',
    'enquiries_open_soon' => 'Online enquiries open soon',
    'tours_closed' => 'Online tour requests open once',
    'example_testimonial' => 'Example testimonial',
];
$visit = function (string $path) use ($kernel, $marks): array {
    $request = Request::create($path, 'GET');
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $body = (string) $response->getContent();

    return array_filter([
        'status' => $response->getStatusCode(),
        'location' => $response->headers->get('Location'),
        'error' => ($response->exception ?? null) ? class_basename($response->exception).': '.mb_substr($response->exception->getMessage(), 0, 300) : null,
        'marks' => array_keys(array_filter($marks, fn (string $mark): bool => str_contains($body, e($mark)) || str_contains($body, $mark))),
    ], fn (mixed $value): bool => $value !== null && $value !== []);
};

$paths = ['/', '/enquire', '/book-a-tour', '/legal/privacy', '/locations', '/locations/noida', '/workspaces', '/customers', '/faqs'];
foreach (Property::query()->publiclyListed()->orderBy('code')->get() as $property) {
    $paths[] = $property->path();
}
foreach (Offering::query()->publiclyListed()->with(['property.city', 'product'])->get() as $offering) {
    $paths[] = $offering->path();
}
foreach (['private-cabins', 'dedicated-desks', 'hot-desks', 'meeting-rooms', 'conference-training-rooms', 'virtual-office', 'managed-offices'] as $slug) {
    $paths[] = '/workspaces/'.$slug;
}
foreach (['example-design-studio', 'example-software-team'] as $slug) {
    $paths[] = '/customers/'.$slug;
}
foreach (MediaAsset::query()->where('title', 'like', 'Demo:%')->orWhere('title', 'like', 'Home banner:%')->get() as $asset) {
    $paths[] = $asset->renditionPath('w960');
}

$result = ['database' => $database->db, 'read_only' => (int) $database->ro, 'pages' => []];
foreach (array_values(array_unique($paths)) as $path) {
    $result['pages'][$path] = $visit($path);
}
$result['statuses'] = array_count_values(array_map(fn (array $page): int => $page['status'], $result['pages']));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
