<?php

namespace App\Http\Middleware;

use App\Models\AppModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleWriteAccess
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $user = $request->user();
        if ($user?->role === 'admin') {
            return $next($request);
        }

        $module = AppModule::query()->where('key', $moduleKey)->first();
        $role = $module
            ? $user?->moduleAccesses()->where('module_id', $module->id)->value('role')
            : null;

        if ($role === AppModule::ROLE_ADVISOR) {
            return $next($request);
        }

        return response()->json(['message' => 'Bu modülde veri değiştirme yetkiniz bulunmuyor.'], Response::HTTP_FORBIDDEN);
    }
}
