<?php

namespace Tests\Support;

use RuntimeException;

/**
 * The one check that the default connection points at a dedicated *_testing database.
 *
 * TestCase runs it before the database traits. The concurrency harnesses run it again before they
 * empty or drop tables, because Pest still runs a test's afterEach hook when TestCase refused in
 * setUp: with a cached configuration pointing at the development database, the harnesses' clean-up
 * emptied `smart_go` on 2026-10-31 (OD-164).
 */
final class TestingDatabase
{
    public static function assertConfigured(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Tests must run against a dedicated *_testing database, but [{$connection}:{$database}] is configured. ".
                'Run `php artisan config:clear` and check phpunit.xml.'
            );
        }
    }
}
