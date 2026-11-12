<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordHasBeenChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->must_change) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Devam etmeden önce şifrenizi değiştirmeniz gerekiyor.',
                'redirect' => route('password.change'),
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('password.change');
    }
}
