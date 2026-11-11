<?php

// SmartGro sample content (OD-143, OD-149, OD-150, OD-165) on the DEMO, local SITE or TEST database only, after
// the kit fixtures (seed-test-fixtures.php), whose staff it uses: author marketing, approver marketing2,
// publisher admin. The content itself, its rules and its rerun behaviour are in Database\Seeders\SampleContentSeeder
// (any other local database: `php artisan db:seed --class=SampleContentSeeder`, as the dummy staff).
//
//   DB_DATABASE=smartgro_demo php docs/operations/scripts/browser/seed-sample-content.php
//
// Prints what it wrote as JSON, with content someone else published or is working on under "left_alone".

use App\Models\User;
use Database\Seeders\SampleContentSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! in_array(DB::selectOne('SELECT DATABASE() AS db')->db, ['smartgro_demo', 'smartgro_site', 'smartgro_testing'], true) || $app->configurationIsCached()) {
    fwrite(STDERR, "Refusing: only the demo, local site or test database, without a configuration cache\n");
    exit(1);
}

$staff = fn (string $key): User => User::query()->where('email', "{$key}@fixture.test")->sole();

echo json_encode(app(SampleContentSeeder::class)->seedWith($staff('marketing'), $staff('marketing2'), $staff('admin'))), PHP_EOL;
