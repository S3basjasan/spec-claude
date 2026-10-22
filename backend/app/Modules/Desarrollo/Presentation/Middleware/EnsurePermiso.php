<?php

namespace App\Modules\Desarrollo\Presentation\Middleware;

use App\Modules\Desarrollo\Application\Services\AutorizadorDeAdecuacion;
use App\Modules\Desarrollo\Application\Services\AutorizadorDeRutas;
use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoRutas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoriza una ruta por clave de permiso (`permiso:desarrollo.rutas.gestionar`, `permiso:desarrollo.adecuacion.ver`).
 * Se usa junto a `auth:sanctum`. Los controladores no leen el nombre del rol: la decisión vive en `AutorizadorDeRutas`
 * y `AutorizadorDeAdecuacion`.
 */
class EnsurePermiso
{
    public function __construct(
        private readonly AutorizadorDeRutas $autorizadorDeRutas,
        private readonly AutorizadorDeAdecuacion $autorizadorDeAdecuacion,
    ) {}

    public function handle(Request $request, Closure $next, string $clave): Response
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $esAdministrador = method_exists($usuario, 'tokenCan') && $usuario->tokenCan('admin');

        if (! $this->permite($esAdministrador, $clave)) {
            return response()->json(['message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        return $next($request);
    }

    private function permite(bool $esAdministrador, string $clave): bool
    {
        $deRutas = ClaveDePermisoRutas::tryFrom($clave);
        if ($deRutas !== null) {
            return $this->autorizadorDeRutas->permite($esAdministrador, $deRutas);
        }

        $deAdecuacion = ClaveDePermisoAdecuacion::tryFrom($clave);

        return $deAdecuacion !== null && $this->autorizadorDeAdecuacion->permite($esAdministrador, $deAdecuacion);
    }
}
