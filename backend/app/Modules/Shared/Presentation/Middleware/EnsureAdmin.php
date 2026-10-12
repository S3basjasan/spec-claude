<?php

namespace App\Modules\Shared\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige autenticación Sanctum y la capacidad `admin`. Se usa junto a `auth:sanctum`.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if (! method_exists($usuario, 'tokenCan') || ! $usuario->tokenCan('admin')) {
            return response()->json(['message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        return $next($request);
    }
}
