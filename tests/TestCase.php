<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Setup\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Most tests exercise a running system; setup-wizard tests call markSetupIncomplete().
        if (in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            $this->app->make(Settings::class)->set('setup.completed_at', now()->toIso8601String());
        }
    }

    protected function markSetupIncomplete(): void
    {
        $this->app->make(Settings::class)->forget('setup.completed_at');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
