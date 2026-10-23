<?php

namespace App\Modules\Desarrollo\Presentation\Middleware;

use App\Modules\Desarrollo\Application\Services\AutorizadorDeAdecuacion;
use App\Modules\Desarrollo\Application\Services\AutorizadorDePdi;
use App\Modules\Desarrollo\Application\Services\AutorizadorDeRutas;
use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoRutas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoriza una ruta por clave de permiso (`permiso:desarrollo.rutas.gestionar`, `permiso:desarrollo.adecuacion.ver`,
 * `permiso:desarrollo.pdi.ver|desarrollo.pdi.ver_propio`). Con varias claves separadas por `|` basta una. Se usa junto a
 * `auth:sanctum`. Los controladores no leen el nombre del rol: la decisión vive en `AutorizadorDeRutas`,
 * `AutorizadorDeAdecuacion` y `AutorizadorDePdi`.
 */
class EnsurePermiso
{
    public function __construct(
        private readonly AutorizadorDeRutas $autorizadorDeRutas,
        private readonly AutorizadorDeAdecuacion $autorizadorDeAdecuacion,
        private readonly AutorizadorDePdi $autorizadorDePdi,
    ) {}

    public function handle(Request $request, Closure $next, string $clave): Response
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $esAdministrador = method_exists($usuario, 'tokenCan') && $usuario->tokenCan('admin');

        foreach (explode('|', $clave) as $alternativa) {
            if ($this->permite($esAdministrador, trim($alternativa))) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'No tienes permisos para realizar esta acción.'], 403);
    }

    private function permite(bool $esAdministrador, string $clave): bool
    {
        $deRutas = ClaveDePermisoRutas::tryFrom($clave);
        if ($deRutas !== null) {
            return $this->autorizadorDeRutas->permite($esAdministrador, $deRutas);
        }

        $deAdecuacion = ClaveDePermisoAdecuacion::tryFrom($clave);
        if ($deAdecuacion !== null) {
            return $this->autorizadorDeAdecuacion->permite($esAdministrador, $deAdecuacion);
        }

        $dePdi = ClaveDePermisoPdi::tryFrom($clave);

        return $dePdi !== null && $this->autorizadorDePdi->permite($esAdministrador, $dePdi);
    }
}
