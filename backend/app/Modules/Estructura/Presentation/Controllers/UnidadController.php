<?php

namespace App\Modules\Estructura\Presentation\Controllers;

use App\Modules\Estructura\Application\UseCases\ActualizarUnidad;
use App\Modules\Estructura\Application\UseCases\CambiarEstadoUnidad;
use App\Modules\Estructura\Application\UseCases\ConsultarImpactoDeDesactivacion;
use App\Modules\Estructura\Application\UseCases\CrearUnidad;
use App\Modules\Estructura\Application\UseCases\ListarOpcionesDeUnidad;
use App\Modules\Estructura\Application\UseCases\ListarUnidades;
use App\Modules\Estructura\Application\UseCases\ObtenerUnidad;
use App\Modules\Estructura\Domain\Exceptions\DesactivacionBloqueada;
use App\Modules\Estructura\Domain\Exceptions\UnidadInvalida;
use App\Modules\Estructura\Domain\Exceptions\UnidadModificada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoReactivable;
use App\Modules\Estructura\Presentation\Requests\CambiarEstadoUnidadRequest;
use App\Modules\Estructura\Presentation\Requests\GuardarUnidadRequest;
use App\Modules\Estructura\Presentation\Requests\ListarUnidadesRequest;
use App\Modules\Estructura\Presentation\Requests\OpcionesDeUnidadRequest;
use App\Modules\Estructura\Presentation\Resources\ImpactoDesactivacionResource;
use App\Modules\Estructura\Presentation\Resources\OpcionesDeUnidadResource;
use App\Modules\Estructura\Presentation\Resources\UnidadDetalleResource;
use App\Modules\Estructura\Presentation\Resources\UnidadResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnidadController
{
    public function index(ListarUnidadesRequest $request, ListarUnidades $listar): JsonResponse
    {
        // La capacidad `admin` del token decide si se pueden ver las unidades inactivas.
        $listado = $listar->ejecutar($request->filtro($this->esAdministrador($request)));

        return UnidadResource::collection($listado->items)
            ->additional(['meta' => ['total' => $listado->total, 'coincidencias' => $listado->coincidencias]])
            ->response();
    }

    public function opciones(OpcionesDeUnidadRequest $request, ListarOpcionesDeUnidad $listar): JsonResponse
    {
        return (new OpcionesDeUnidadResource($listar->ejecutar($request->reubicar())))->response();
    }

    public function impacto(int $unidadId, ConsultarImpactoDeDesactivacion $consultar): JsonResponse
    {
        return $this->responder(fn () => (new ImpactoDesactivacionResource($consultar->ejecutar($unidadId)))->response());
    }

    public function show(Request $request, int $unidadId, ObtenerUnidad $obtener): JsonResponse
    {
        return $this->responder(fn () => (new UnidadDetalleResource(
            $obtener->ejecutar($unidadId, $this->esAdministrador($request)),
        ))->response());
    }

    public function store(GuardarUnidadRequest $request, CrearUnidad $crear): JsonResponse
    {
        return $this->responder(fn () => (new UnidadDetalleResource(
            $crear->ejecutar($request->datos(), $this->actor($request)),
        ))->response()->setStatusCode(201));
    }

    public function update(int $unidadId, GuardarUnidadRequest $request, ActualizarUnidad $actualizar): JsonResponse
    {
        return $this->responder(fn () => (new UnidadDetalleResource(
            $actualizar->ejecutar(
                $unidadId,
                (int) $request->validated('version'),
                $request->datos(),
                $request->codigoEnviado(),
                $this->actor($request),
            ),
        ))->response());
    }

    public function estado(int $unidadId, CambiarEstadoUnidadRequest $request, CambiarEstadoUnidad $cambiar): JsonResponse
    {
        return $this->responder(fn () => (new UnidadDetalleResource(
            $cambiar->ejecutar($unidadId, $request->estado(), $this->actor($request)),
        ))->response());
    }

    private function esAdministrador(Request $request): bool
    {
        return $request->user()->tokenCan('admin');
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
        } catch (UnidadNoEncontrada $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (UnidadModificada $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (UnidadInvalida $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => array_map(fn (string $mensaje) => [$mensaje], $e->errores),
            ], 422);
        } catch (DesactivacionBloqueada $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['estado' => [$e->getMessage()]],
                'unidades_con_posiciones' => $e->unidades,
            ], 422);
        } catch (UnidadNoReactivable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['estado' => [$e->getMessage()]],
                'motivo' => $e->motivo,
            ], 422);
        }
    }
}
