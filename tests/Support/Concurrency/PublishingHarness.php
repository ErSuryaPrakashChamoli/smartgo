<?php

namespace Tests\Support\Concurrency;

use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Runs publish and take-down attempts in parallel PHP processes against committed data in the testing
 * database, to prove that both lock the content and its versions (ADR-0005, SECURITY_MODEL §11.6, OD-98).
 */
final class PublishingHarness
{
    /**
     * Tables the publishing tests fill with committed rows; emptied before and after each test.
     *
     * @var list<string>
     */
    private const TABLES = [
        'activity_log', 'redirects', 'revisions', 'pages', 'testimonials', 'case_studies',
        'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'roles', 'permissions', 'users',
    ];

    public static function reset(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            foreach (self::TABLES as $table) {
                DB::table($table)->truncate();
            }
        });
    }

    /**
     * Run one worker per attempt, all released at the same instant.
     *
     * @param  list<array{mode: 'manual'|'scheduled'|'takedown'|'submit'|'approve'|'restore', revision_id?: int, user_id?: int, testimonial_id?: int, consent_withdrawn?: bool, start_delay_ms?: int}>  $attempts
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
                        'PUBLISH_RELEASE_AT' => (string) $releaseAt,
                        'PUBLISH_MODE' => $attempt['mode'],
                        'PUBLISH_REVISION_ID' => (string) ($attempt['revision_id'] ?? ''),
                        'PUBLISH_USER_ID' => (string) ($attempt['user_id'] ?? ''),
                        'PUBLISH_TESTIMONIAL_ID' => (string) ($attempt['testimonial_id'] ?? ''),
                        'PUBLISH_CONSENT_WITHDRAWN' => ($attempt['consent_withdrawn'] ?? false) ? '1' : '0',
                        'PUBLISH_START_DELAY_MS' => (string) ($attempt['start_delay_ms'] ?? 0),
                    ])
                    ->command([PHP_BINARY, 'tests/Support/Concurrency/publish-worker.php']);
            }
        });

        $outcomes = [];

        foreach ($results as $name => $result) {
            if ($result->failed()) {
                throw new RuntimeException("Publishing worker {$name} failed: ".trim($result->errorOutput()));
            }

            $outcomes[$name] = json_decode(trim($result->output()), true, flags: JSON_THROW_ON_ERROR);
        }

        return $outcomes;
    }
}
