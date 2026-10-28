<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// SmartGro browser checks: confirms which database this environment reaches, read-only.
//
//   EXPECT_DB=smartgro_testing php docs/operations/scripts/browser/check-target.php
//
// Prints the effective configuration and the database the connection actually reaches, and exits
// non-zero unless both are the expected isolated database (EXPECT_DB, default smartgro_testing) and the
// configuration is not cached. `smart_go` is never accepted. Writes nothing.
$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$row = DB::selectOne('SELECT DATABASE() AS db, @@hostname AS host, @@port AS port');
$expected = getenv('EXPECT_DB') ?: 'smartgro_testing';
$result = [
    'expected_database' => $expected,
    'configured_database' => config('database.connections.mysql.database'),
    'connected_database' => $row->db,
    'server' => $row->host.':'.$row->port,
    'config_cached' => $app->configurationIsCached(),
    'app_env' => config('app.env'),
];

echo json_encode($result, JSON_PRETTY_PRINT), PHP_EOL;

exit($expected !== 'smart_go' && ! $result['config_cached'] && $row->db === $expected && $result['configured_database'] === $expected ? 0 : 1);
