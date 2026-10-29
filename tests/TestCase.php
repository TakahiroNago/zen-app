<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'zen.prompts' => require __DIR__.'/Fixtures/zen-prompts.php',
        ]);
    }
}
