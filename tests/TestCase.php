<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Indicates whether the default seeder should run before each test.
     * Required for tests that rely on pre-populated reference/core data.
     *
     * @var bool
     */
    protected $seed = true;
}
