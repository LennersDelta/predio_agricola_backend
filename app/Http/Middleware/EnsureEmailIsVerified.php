<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * El sistema autentica por RUT, no por email.
     * Este middleware solo verifica que el usuario esté autenticado.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        return $next($request);
    }
}