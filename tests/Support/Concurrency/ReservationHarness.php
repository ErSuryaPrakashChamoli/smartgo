<?php

namespace Tests\Support\Concurrency;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Throwaway schema and parallel worker runner used to prove MySQL concurrency guarantees
 * (ADR-0004) before any SmartGro booking code exists. The tables are not domain tables.
 */
final class ReservationHarness
{
    public const string RESOURCES = 'harness_resources';

    public const string RESERVATIONS = 'harness_reservations';

    /**
     * Strategy that locks the parent resource row before reading anything else (ADR-0004).
     */
    public const string LOCKED = 'locked';

    /**
     * Check-then-insert without any lock: the classic double-booking race.
     */
    public const string NAIVE = 'naive';

    /**
     * Takes a plain (snapshot) read before locking, so the post-lock check reads a stale snapshot.
     */
    public const string STALE_SNAPSHOT = 'stale_snapshot';

    public static function createTables(): void
    {
        self::dropTables();

        Schema::create(self::RESOURCES, function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('capacity');
        });

        Schema::create(self::RESERVATIONS, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_id')->constrained(self::RESOURCES);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('worker');
            $table->index(['resource_id', 'starts_at', 'ends_at']);
        });
    }

    public static function dropTables(): void
    {
        Schema::dropIfExists(self::RESERVATIONS);
        Schema::dropIfExists(self::RESOURCES);
    }

    /**
     * Run one worker process per request, all released at the same instant.
     *
     * @param  list<array{starts_at: string, ends_at: string, quantity?: int}>  $requests
     * @param  'exclusive'|'capacity'  $kind
     * @return array<string, string> worker name => "confirmed" or "conflict"
     */
    public static function runWorkers(int $resourceId, array $requests, string $strategy, string $kind, int $delayMilliseconds = 300): array
    {
        $connection = config('database.connections.'.config('database.default'));
        $releaseAt = microtime(true) + 3;

        $results = Process::concurrently(function (Pool $pool) use ($requests, $connection, $releaseAt, $resourceId, $strategy, $kind, $delayMilliseconds): void {
            foreach ($requests as $index => $request) {
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
                        'HARNESS_WORKER' => "worker-{$index}",
                        'HARNESS_RELEASE_AT' => (string) $releaseAt,
                        'HARNESS_RESOURCE_ID' => (string) $resourceId,
                        'HARNESS_STRATEGY' => $strategy,
                        'HARNESS_KIND' => $kind,
                        'HARNESS_STARTS_AT' => $request['starts_at'],
                        'HARNESS_ENDS_AT' => $request['ends_at'],
                        'HARNESS_QUANTITY' => (string) ($request['quantity'] ?? 1),
                        'HARNESS_DELAY_MS' => (string) $delayMilliseconds,
                    ])
                    ->command([PHP_BINARY, 'tests/Support/Concurrency/reservation-worker.php']);
            }
        });

        $outcomes = [];

        foreach ($results as $name => $result) {
            if ($result->failed()) {
                throw new RuntimeException("Harness {$name} failed: ".trim($result->errorOutput()));
            }

            $outcomes[$name] = json_decode(trim($result->output()), true, flags: JSON_THROW_ON_ERROR)['outcome'];
        }

        return $outcomes;
    }

    /**
     * Count outcomes in a fixed key order, independent of which worker finished first.
     *
     * @param  array<string, string>  $outcomes
     * @return array{confirmed: int, conflict: int}
     */
    public static function tally(array $outcomes): array
    {
        $counts = array_count_values($outcomes);

        return [
            'confirmed' => $counts['confirmed'] ?? 0,
            'conflict' => $counts['conflict'] ?? 0,
        ];
    }

    /**
     * Number of reservation pairs on the same resource whose time ranges overlap.
     */
    public static function overlappingPairs(): int
    {
        return DB::table(self::RESERVATIONS.' as first')
            ->join(self::RESERVATIONS.' as second', function ($join): void {
                $join->on('first.resource_id', '=', 'second.resource_id')
                    ->on('first.id', '<', 'second.id')
                    ->on('first.starts_at', '<', 'second.ends_at')
                    ->on('first.ends_at', '>', 'second.starts_at');
            })
            ->count();
    }
}
