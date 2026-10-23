<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run database traits against anything other than a dedicated testing database.
     *
     * Cached configuration ignores the environment forced by phpunit.xml, which could otherwise
     * point RefreshDatabase at the development database and wipe it.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits(): array
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Tests must run against a dedicated *_testing database, but [{$connection}:{$database}] is configured. ".
                'Run `php artisan config:clear` and check phpunit.xml.'
            );
        }

        return parent::setUpTraits();
    }
}
