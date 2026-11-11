<?php

// OD-181: read-only smoke test of the location pages' 3D walkthrough and floor plans: php explore-smoke.php <expected database>
// Boots the app on the expected database with a READ ONLY database session (every write fails), array session and cache,
// then handles GET requests in-process (no server, no request to the owner's ports): the home page, every location page,
// each shown 3D model file and floor plan image, and, as the database's active Super Admin, the 3D models screens and
// each location's profile editor. Prints each status, what the explore section holds, and the exception behind any 500.

use App\Filament\Clusters\Properties\Resources\PropertyProfiles\PropertyProfileResource;
use App\Filament\Clusters\Website\Resources\TwinModels\TwinModelResource;
use App\Models\Property;
use App\Models\PropertyProfile;
use App\Models\TwinModel;
use App\Models\User;
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
$visit = function (string $path) use ($kernel): array {
    $request = Request::create($path, 'GET');
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $body = $response instanceof Symfony\Component\HttpFoundation\BinaryFileResponse ? '' : (string) $response->getContent();

    return ['status' => $response->getStatusCode(), 'type' => $response->headers->get('Content-Type'), 'body' => $body, 'error' => ($exception = $response->exception ?? null) ? class_basename($exception).': '.mb_substr($exception->getMessage(), 0, 300) : null];
};
$summary = fn (array $page): array => array_filter(['status' => $page['status'], 'type' => $page['type'], 'error' => $page['error']], fn ($value): bool => $value !== null);

$result = ['database' => $database->db, 'read_only' => (int) $database->ro, 'explore_columns' => PropertyProfile::hasExplore(), 'public' => [], 'backoffice' => []];

$home = $visit('/');
$result['public']['/'] = [...$summary($home), 'not_available_yet' => str_contains($home['body'], 'Not available yet'), 'explore_link' => preg_match('#href="([^"]*\#explore)"#', $home['body'], $m) === 1 ? $m[1] : null];

foreach (Property::query()->with('city')->orderBy('id')->get() as $property) {
    $path = (string) ($property->path() ?? '/locations/'.$property->city->slug.'/'.$property->slug);
    $page = $visit($path);
    $body = $page['body'];
    $result['public'][$path] = [
        ...$summary($page),
        'explore' => str_contains($body, 'id="explore"'),
        'walkthrough' => str_contains($body, 'data-twin-walkthrough'),
        'notice' => str_contains($body, 'Conceptual illustration — not a scan of this location'),
        'views' => substr_count($body, 'data-twin-camera='),
        'floor_plans' => substr_count($body, 'data-floor-plan>'),
        'example_plan_labels' => substr_count($body, 'Example floor plan — not SmartGro&#039;s layout'),
        'three_d_script_or_preload' => preg_match('/<(script|link)\b[^>]*twin-viewer/', $body) === 1,
    ];

    if (preg_match('#data-model="(/3d-models/\d+)"#', $body, $model) === 1) {
        $result['public'][$model[1]] = $summary($visit($model[1]));
    }

    preg_match_all('#<a href="(/media/\d+/w1600)"#', $body, $plans);
    foreach (array_unique($plans[1]) as $plan) {
        $result['public'][$plan] = $summary($visit($plan));
        $preview = str_replace('w1600', 'w960', $plan);
        $result['public'][$preview] = $summary($visit($preview));
    }
}

$result['public']['/3d-models/999999'] = $summary($visit('/3d-models/999999'));

$admin = User::query()->where('is_active', true)->whereHas('roles', fn ($roles) => $roles->where('name', 'super_admin'))->orderBy('id')->first();
$result['acting_as'] = $admin?->id;
if ($admin !== null) {
    // As OD-178's smoke: the user is set on the guard, so no sign-in is recorded (the session is read only).
    auth()->guard('web')->setUser($admin);
    $result['backoffice'][TwinModelResource::getUrl('index', isAbsolute: false)] = $summary($visit(TwinModelResource::getUrl('index', isAbsolute: false)));
    foreach (TwinModel::query()->orderBy('id')->get() as $model) {
        $url = TwinModelResource::getUrl('view', ['record' => $model], isAbsolute: false);
        $result['backoffice'][$url] = $summary($visit($url));
    }
    foreach (PropertyProfile::query()->orderBy('id')->get() as $profile) {
        $url = PropertyProfileResource::getUrl('edit', ['record' => $profile], isAbsolute: false);
        $page = $visit($url);
        $result['backoffice'][$url] = [...$summary($page), 'explore_section' => str_contains($page['body'], '3D walkthrough and floor plans')];
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
