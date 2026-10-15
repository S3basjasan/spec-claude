<?php

namespace App\Modules\Estructura\Presentation\Controllers;

use App\Modules\Estructura\Application\UseCases\CrearTipoDeUnidad;
use App\Modules\Estructura\Application\UseCases\GuardarCadenaDeTipos;
use App\Modules\Estructura\Application\UseCases\ListarTiposDeUnidad;
use App\Modules\Estructura\Domain\Exceptions\CadenaInvalida;
use App\Modules\Estructura\Domain\Exceptions\CadenaModificada;
use App\Modules\Estructura\Domain\ValueObjects\UnidadAfectadaPorCadena;
use App\Modules\Estructura\Presentation\Requests\CrearTipoUnidadRequest;
use App\Modules\Estructura\Presentation\Requests\GuardarCadenaRequest;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoUnidadController
{
    public function index(ListarTiposDeUnidad $listar): JsonResponse
    {
        $cadena = $listar->ejecutar();

        return response()->json(['data' => $cadena->tipos, 'meta' => ['version' => $cadena->version]]);
    }

    public function store(CrearTipoUnidadRequest $request, CrearTipoDeUnidad $crear): JsonResponse
    {
        return $this->responder(fn () => response()->json(
            ['data' => $crear->ejecutar($request->nombre(), $this->actor($request))],
            201,
        ));
    }

    public function update(GuardarCadenaRequest $request, GuardarCadenaDeTipos $guardar): JsonResponse
    {
        return $this->responder(function () use ($request, $guardar) {
            $cadena = $guardar->ejecutar($request->version(), $request->borrador(), $this->actor($request));

            return response()->json(['data' => $cadena->tipos, 'meta' => ['version' => $cadena->version]]);
        });
    }

    private function actor(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }

    /**
     * Traduce las excepciones de dominio a respuestas HTTP sin revelar detalles internos.
     *
     * @param  Closure(): JsonResponse  $accion
     */
    private function responder(Closure $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (CadenaModificada $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (CadenaInvalida $e) {
            $cuerpo = [
                'message' => $e->getMessage(),
                'errors' => array_map(fn (string $mensaje) => [$mensaje], $e->errores),
            ];
            if ($e->unidadesInvalidas !== []) {
                $cuerpo['unidades_invalidas'] = array_map(fn (UnidadAfectadaPorCadena $u) => [
                    'id' => $u->id,
                    'codigo' => $u->codigo,
                    'nombre' => $u->nombre,
                    'tipo' => ['nombre' => $u->tipoNombre, 'nivel' => $u->tipoNivel],
                    'unidad_padre' => [
                        'id' => $u->padreId,
                        'codigo' => $u->padreCodigo,
                        'nombre' => $u->padreNombre,
                        'tipo' => ['nombre' => $u->padreTipoNombre, 'nivel' => $u->padreTipoNivel],
                    ],
                ], $e->unidadesInvalidas);
            }

            return response()->json($cuerpo, 422);
        }
    }
}
