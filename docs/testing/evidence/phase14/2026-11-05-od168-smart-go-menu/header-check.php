<?php

// OD-168: the home page's header as a database renders it, read-only (READ ONLY session, array session and cache,
// in-process: no server). Prints each main-menu drop-down and the links it shows. Usage: php header-check.php <database>

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
$request = Request::create('/', 'GET');
$response = $kernel->handle($request);
$kernel->terminate($request, $response);

$dom = new DOMDocument;
@$dom->loadHTML((string) $response->getContent());
$xpath = new DOMXPath($dom);
$menus = [];
foreach ($xpath->query('//nav[@aria-label="Main" and contains(@class, "hidden")]//button[@popovertarget]') as $button) {
    $panel = $dom->getElementById($button->getAttribute('popovertarget'));
    $links = [];
    foreach ($xpath->query('.//a', $panel) as $link) {
        $links[] = trim(preg_replace('/\s+/', ' ', $xpath->evaluate('string(.//span[contains(@class, "font-medium")] | .)', $link) ?: $link->textContent)).' -> '.$link->getAttribute('href');
    }
    $menus[trim($button->textContent)] = ['links' => count($links), 'first' => array_slice($links, 0, 3), 'sample_note' => str_contains($panel->textContent, 'Sample pages')];
}

echo json_encode(['database' => $database->db, 'read_only' => (int) $database->ro, 'status' => $response->getStatusCode(), 'drop_downs' => $menus], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
