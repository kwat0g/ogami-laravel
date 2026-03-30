<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests seed only the fixtures they need.
     *
     * @var bool
     */
    protected $seed = false;
}
