<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Route;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    protected function skipUnlessRouteExists(string $route, ?string $message = null): void
    {
        if (! Route::has($route)) {
            $this->markTestSkipped($message ?? "Route [{$route}] is not registered by this application.");
        }
    }
}
