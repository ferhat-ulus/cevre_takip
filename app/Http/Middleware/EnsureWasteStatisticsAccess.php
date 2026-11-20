<?php

namespace App\Http\Middleware;

use App\Models\AppModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWasteStatisticsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $role = $user?->moduleAccesses()
            ->whereHas('module', fn ($query) => $query->where('key', 'waste'))
            ->value('role');

        abort_unless($user?->role === 'admin' || $role === AppModule::ROLE_VIEWER, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
