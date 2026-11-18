<?php

namespace App\Http\Middleware;

use App\Models\AppModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWasteModuleSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(AppModule::where('key', 'waste')->where('is_active', true)->exists(), 404);
        if ($request->routeIs('modules.*') || $request->session()->get('active_module') === 'waste') {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Önce Atık Takip modülünü seçin.'], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('modules.index');
    }
}
