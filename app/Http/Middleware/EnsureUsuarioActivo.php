<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Una cuenta desactivada por la dueña deja de poder usar su token. */
class EnsureUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->activo) {
            return response()->json(['message' => 'La cuenta está desactivada.'], 403);
        }

        return $next($request);
    }
}
