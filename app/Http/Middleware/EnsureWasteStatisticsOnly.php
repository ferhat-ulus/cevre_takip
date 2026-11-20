<?php

namespace App\Http\Middleware;

use App\Models\AppModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWasteStatisticsOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->role === 'admin') {
            return $next($request);
        }

        $role = $user?->moduleAccesses()
            ->whereHas('module', fn ($query) => $query->where('key', 'waste'))
            ->value('role');

        if ($role !== AppModule::ROLE_VIEWER || $request->routeIs('waste.statistics')) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            return redirect()->route('waste.statistics');
        }

        abort(Response::HTTP_FORBIDDEN, 'Bu kullanıcı yalnızca atık takip istatistiklerini görüntüleyebilir.');
    }
}
