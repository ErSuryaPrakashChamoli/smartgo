<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestingDatabase;

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
        TestingDatabase::assertConfigured();

        return parent::setUpTraits();
    }
}
