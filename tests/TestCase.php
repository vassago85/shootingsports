<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The test workflow does not build Vite assets, so @vite 500s without a manifest.
        $this->withoutVite();
    }
}
