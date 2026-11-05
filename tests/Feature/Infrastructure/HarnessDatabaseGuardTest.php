<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\InventoryHarness;
use Tests\Support\Concurrency\PublishingHarness;
use Tests\Support\Concurrency\ReservationHarness;

/*
 * OD-164: Pest runs afterEach even when TestCase refused the configured database in setUp, and on
 * 2026-10-31 the harnesses' clean-up emptied `smart_go` that way. Only the configured name changes
 * here; the open connection stays on the testing database, so a missing guard empties test tables.
 */
it('refuses to empty or drop tables while the configured database is not a testing database', function (callable $cleanUp) {
    $connection = config('database.default');
    config(["database.connections.{$connection}.database" => 'smart_go']);
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    expect(fn () => $cleanUp())->toThrow(RuntimeException::class, "[{$connection}:smart_go]");

    expect($queries)->toBeEmpty();
})->with([
    'publishing harness reset' => [[PublishingHarness::class, 'reset']],
    'inventory harness reset' => [[InventoryHarness::class, 'reset']],
    'reservation harness drop' => [[ReservationHarness::class, 'dropTables']],
    'reservation harness create' => [[ReservationHarness::class, 'createTables']],
]);
