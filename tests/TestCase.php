<?php

namespace Tests;

use App\Services\ImageGeneration\Providers\FakeImageGenerator;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /** Every test starts with the permission catalog and system roles in place, as a deployed app has them. */
    protected bool $seed = true;

    protected string $seeder = AuthorizationSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake adapters keep state in statics, so each test starts clean.
        FakeImageGenerator::flush();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
