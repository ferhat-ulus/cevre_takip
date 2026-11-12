<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Route;
use App\Models\AppModule;
use App\Models\ModuleUserAccess;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(UserContract $user, $guard = null)
    {
        if ($user->role !== 'admin' && AppModule::query()->where('key', 'environment')->exists()) {
            $environmentModuleId = AppModule::query()->where('key', 'environment')->value('id');
            ModuleUserAccess::query()->firstOrCreate([
                'user_id' => $user->getAuthIdentifier(),
                'module_id' => $environmentModuleId,
            ], ['role' => AppModule::ROLE_ADVISOR]);
        }

        $this->withSession([
            'active_module' => 'environment',
            'auth_session_version' => (int) $user->sess_ver,
        ]);

        return parent::actingAs($user, $guard);
    }

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
