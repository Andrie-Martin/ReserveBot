<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Some presentation tests boot a second, standalone application (tests/Support/foundations-runtime.php) and
        // PHPUnit runs every test in one process. Whatever one of them left behind is cleared before each test's own
        // application is created, so no test depends on its predecessors cleaning up after themselves.
        if (function_exists('foundationsRelease')) {
            foundationsRelease();
        }

        parent::setUp();
    }
}
