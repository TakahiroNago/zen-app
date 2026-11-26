<?php

namespace Tests;

use App\Services\PersonalInfoScanner;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakePersonalInfoScanner;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'zen.prompts' => require __DIR__.'/Fixtures/zen-prompts.php',
        ]);

        // No test reaches llm-inspect-server by accident. A test that needs personal information
        // to be found says what the scanner reports: personalInfoScanner()->flag(...).
        $this->app->instance(PersonalInfoScanner::class, new FakePersonalInfoScanner);
    }
}
