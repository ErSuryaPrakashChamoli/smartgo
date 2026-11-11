<?php

// OD-178: read-only check of the pages the seed gave a new version (revisions after <id>) and of every internal link on them.
// Usage: php link-check.php <expected database> <revision id ceiling>. READ ONLY database session, array session and cache, in-process.

use App\Models\Page;
use App\Models\Revision;
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
    fwrite(STDERR, "Refusing: database {$database->db}, read only {$database->ro}\n");
    exit(1);
}

$kernel = $app->make(HttpKernel::class);
$get = function (string $path) use ($kernel): array {
    $request = Request::create($path, 'GET');
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), (string) $response->getContent()];
};

$pageIds = Revision::query()->where('id', '>', (int) $argv[2])->where('revisionable_type', Page::class)->distinct()->pluck('revisionable_id');
$result = ['database' => $database->db, 'pages' => [], 'links' => []];
$links = [];
foreach (Page::query()->whereKey($pageIds)->orderBy('id')->get() as $page) {
    $path = $page->path() ?? '/'.$page->slug;
    [$status, $body] = $get($path);
    $result['pages'][$path] = $status;
    preg_match_all('#<main.*?</main>#s', $body, $main);
    preg_match_all('#href="(/[^"\#?]*)#', $main[0][0] ?? '', $hrefs);
    foreach ($hrefs[1] as $href) {
        $links[$href][] = $path;
    }
}
ksort($links);
foreach ($links as $href => $from) {
    $result['links'][$href] = $get($href)[0];
}
$result['not_ok'] = array_filter($result['links'], fn (int $status): bool => ! in_array($status, [200, 301, 302], true));
$result['still_read_only'] = (int) DB::selectOne('SELECT @@session.transaction_read_only AS ro')->ro;

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
