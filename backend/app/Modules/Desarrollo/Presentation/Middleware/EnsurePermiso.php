<?php

namespace App\Modules\Desarrollo\Presentation\Middleware;

use App\Modules\Desarrollo\Application\Services\AutorizadorDeRutas;
use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoRutas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoriza una ruta por clave de permiso (`permiso:desarrollo.rutas.gestionar`). Se usa junto a `auth:sanctum`.
 * Los controladores no leen el nombre del rol: la decisión vive en `AutorizadorDeRutas`.
 */
class EnsurePermiso
{
    public function __construct(private readonly AutorizadorDeRutas $autorizador) {}

    public function handle(Request $request, Closure $next, string $clave): Response
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $permiso = ClaveDePermisoRutas::tryFrom($clave);
        $esAdministrador = method_exists($usuario, 'tokenCan') && $usuario->tokenCan('admin');

        if ($permiso === null || ! $this->autorizador->permite($esAdministrador, $permiso)) {
            return response()->json(['message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        return $next($request);
    }
}
