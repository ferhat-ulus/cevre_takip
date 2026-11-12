<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEnvironmentModuleSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('modules.*') || $request->session()->get('active_module') === 'environment') {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Önce kullanmak istediğiniz modülü seçin.'], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('modules.index');
    }
}
