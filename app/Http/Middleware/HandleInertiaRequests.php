<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $moduleAccesses = $user?->moduleAccesses()->with('module:id,key')->get()
            ->mapWithKeys(fn ($access) => [$access->module->key => $access->role])->all() ?? [];

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    // Rolü zorla küçük harfe çevirerek gönderiyoruz
                    'role' => strtolower(trim($user->role)),
                    'active' => (bool) $user->active,
                    'module_accesses' => $moduleAccesses,
                ] : null,
            ],
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
            ],
        ]);
    }
}
