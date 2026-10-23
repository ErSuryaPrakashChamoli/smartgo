<?php

/*
 * Test-only worker for Tests\Support\Concurrency\ReservationHarness.
 *
 * Boots the application, waits for the shared release instant, then tries to reserve
 * a harness resource with the requested strategy. Prints one JSON line with the outcome.
 * It never runs outside the MySQL concurrency harness tests.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\ReservationHarness;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_ends_with((string) DB::connection()->getDatabaseName(), '_testing')) {
    fwrite(STDERR, 'Refusing to run the harness outside a *_testing database.'.PHP_EOL);
    exit(1);
}

$resourceId = (int) getenv('HARNESS_RESOURCE_ID');
$strategy = (string) getenv('HARNESS_STRATEGY');
$kind = (string) getenv('HARNESS_KIND');
$startsAt = (string) getenv('HARNESS_STARTS_AT');
$endsAt = (string) getenv('HARNESS_ENDS_AT');
$quantity = (int) getenv('HARNESS_QUANTITY');
$delayMicroseconds = (int) getenv('HARNESS_DELAY_MS') * 1000;
$worker = (string) getenv('HARNESS_WORKER');

DB::connection()->getPdo();

$waitMicroseconds = (int) round(((float) getenv('HARNESS_RELEASE_AT') - microtime(true)) * 1_000_000);

if ($waitMicroseconds > 0) {
    usleep($waitMicroseconds);
}

$overlapping = fn () => DB::table(ReservationHarness::RESERVATIONS)
    ->where('resource_id', $resourceId)
    ->where('starts_at', '<', $endsAt)
    ->where('ends_at', '>', $startsAt);

$outcome = DB::transaction(function () use ($overlapping, $resourceId, $strategy, $kind, $startsAt, $endsAt, $quantity, $delayMicroseconds, $worker): string {
    if ($strategy === ReservationHarness::STALE_SNAPSHOT) {
        $overlapping()->exists();
    }

    $resource = $strategy === ReservationHarness::NAIVE
        ? DB::table(ReservationHarness::RESOURCES)->where('id', $resourceId)->first()
        : DB::table(ReservationHarness::RESOURCES)->where('id', $resourceId)->lockForUpdate()->first();

    $isAvailable = $kind === 'exclusive'
        ? ! $overlapping()->exists()
        : (int) $overlapping()->sum('quantity') + $quantity <= $resource->capacity;

    if (! $isAvailable) {
        return 'conflict';
    }

    usleep($delayMicroseconds);

    DB::table(ReservationHarness::RESERVATIONS)->insert([
        'resource_id' => $resourceId,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'quantity' => $quantity,
        'worker' => $worker,
    ]);

    return 'confirmed';
}, attempts: 3);

echo json_encode(['worker' => $worker, 'outcome' => $outcome]), PHP_EOL;
