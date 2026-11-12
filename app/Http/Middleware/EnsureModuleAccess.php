<?php

namespace App\Http\Middleware;

use App\Models\AppModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $user = $request->user();
        $module = AppModule::query()->where('key', $moduleKey)->first();

        $hasAccess = $user?->role === 'admin'
            || ($module && $module->is_active && $user?->moduleAccesses()->where('module_id', $module->id)->exists());

        if ($hasAccess) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Bu modüle erişim yetkiniz bulunmuyor.'], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('modules.index')->with('error', 'Bu modüle erişim yetkiniz bulunmuyor.');
    }
}
