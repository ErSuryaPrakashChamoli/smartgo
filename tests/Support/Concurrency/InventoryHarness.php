<?php

namespace Tests\Support\Concurrency;

use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Runs inventory changes (allocations, status changes, seat changes) and price plan activations in
 * parallel PHP processes against committed data in the testing database, to prove that each locks its
 * row (the space's, or the offering's for prices) before reading anything (ADR-0004, BR-BK-5, BR-PR-2).
 */
final class InventoryHarness
{
    /**
     * Inventory tables, emptied with the publishing harness's tables before and after each test.
     *
     * @var list<string>
     */
    private const array TABLES = ['offer_offering', 'offers', 'price_plans', 'space_allocations', 'space_seats', 'offering_space', 'spaces', 'floors', 'space_types'];

    public static function reset(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            foreach (self::TABLES as $table) {
                DB::table($table)->truncate();
            }
        });

        PublishingHarness::reset();
    }

    /**
     * Run one worker per attempt, all released at the same instant.
     *
     * @param  list<array{mode: 'allocate'|'status'|'deactivate_seat'|'activate_plan', space_id: int, user_id: int, data?: array<string, mixed>}>  $attempts
     * @return array<string, array{outcome: string, message: string|null}> worker name => result
     */
    public static function runWorkers(array $attempts): array
    {
        $connection = config('database.connections.'.config('database.default'));
        $releaseAt = microtime(true) + 3;

        $results = Process::concurrently(function (Pool $pool) use ($attempts, $connection, $releaseAt): void {
            foreach ($attempts as $index => $attempt) {
                $pool->as("worker-{$index}")
                    ->path(base_path())
                    ->timeout(60)
                    ->env([
                        'APP_ENV' => 'testing',
                        'CACHE_STORE' => 'array',
                        'SESSION_DRIVER' => 'array',
                        'QUEUE_CONNECTION' => 'sync',
                        'DB_URL' => '',
                        'DB_CONNECTION' => 'mysql',
                        'DB_HOST' => (string) $connection['host'],
                        'DB_PORT' => (string) $connection['port'],
                        'DB_DATABASE' => (string) $connection['database'],
                        'DB_USERNAME' => (string) $connection['username'],
                        'DB_PASSWORD' => (string) $connection['password'],
                        'DB_SOCKET' => (string) $connection['unix_socket'],
                        'INVENTORY_RELEASE_AT' => (string) $releaseAt,
                        'INVENTORY_MODE' => $attempt['mode'],
                        'INVENTORY_SPACE_ID' => (string) $attempt['space_id'],
                        'INVENTORY_USER_ID' => (string) $attempt['user_id'],
                        'INVENTORY_DATA' => json_encode($attempt['data'] ?? [], JSON_THROW_ON_ERROR),
                    ])
                    ->command([PHP_BINARY, 'tests/Support/Concurrency/inventory-worker.php']);
            }
        });

        $outcomes = [];

        foreach ($results as $name => $result) {
            if ($result->failed()) {
                throw new RuntimeException("Inventory worker {$name} failed: ".trim($result->errorOutput()));
            }

            $outcomes[$name] = json_decode(trim($result->output()), true, flags: JSON_THROW_ON_ERROR);
        }

        return $outcomes;
    }
}
