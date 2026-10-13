<?php

namespace App\Modules\Estructura\Presentation\Controllers;

use App\Modules\Estructura\Application\UseCases\ActualizarPuesto;
use App\Modules\Estructura\Application\UseCases\CambiarEstadoPuesto;
use App\Modules\Estructura\Application\UseCases\CrearPuesto;
use App\Modules\Estructura\Application\UseCases\ListarCompetenciasParaPuestos;
use App\Modules\Estructura\Application\UseCases\ListarPuestos;
use App\Modules\Estructura\Application\UseCases\ObtenerPuesto;
use App\Modules\Estructura\Domain\Exceptions\CodigoDePuestoNoEditable;
use App\Modules\Estructura\Domain\Exceptions\CompetenciasDePuestoInvalidas;
use App\Modules\Estructura\Domain\Exceptions\NombreDePuestoDuplicado;
use App\Modules\Estructura\Domain\Exceptions\PuestoConPosicionesEnUso;
use App\Modules\Estructura\Domain\Exceptions\PuestoModificado;
use App\Modules\Estructura\Domain\Exceptions\PuestoNoEncontrado;
use App\Modules\Estructura\Presentation\Requests\CambiarEstadoPuestoRequest;
use App\Modules\Estructura\Presentation\Requests\GuardarPuestoRequest;
use App\Modules\Estructura\Presentation\Requests\ListarPuestosRequest;
use App\Modules\Estructura\Presentation\Resources\CompetenciaParaPuestoResource;
use App\Modules\Estructura\Presentation\Resources\PuestoDetalleResource;
use App\Modules\Estructura\Presentation\Resources\PuestoResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PuestoController
{
    public function index(ListarPuestosRequest $request, ListarPuestos $listar): JsonResponse
    {
        // La capacidad `admin` del token decide si se pueden ver los puestos inactivos.
        $listado = $listar->ejecutar($request->filtro($request->user()->tokenCan('admin')));

        $paginador = (new LengthAwarePaginator(
            $listado->items,
            $listado->total,
            $listado->porPagina,
            $listado->pagina,
            ['path' => $request->url()],
        ))->appends($request->query());

        return PuestoResource::collection($paginador)->response();
    }

    public function disponibles(ListarCompetenciasParaPuestos $listar): JsonResponse
    {
        return CompetenciaParaPuestoResource::collection(collect($listar->ejecutar()))->response();
    }

    public function show(int $puestoId, ObtenerPuesto $obtener): JsonResponse
    {
        return $this->responder(fn () => (new PuestoDetalleResource($obtener->ejecutar($puestoId)))->response());
    }

    public function store(GuardarPuestoRequest $request, CrearPuesto $crear): JsonResponse
    {
        return $this->responder(fn () => (new PuestoDetalleResource(
            $crear->ejecutar($request->datos(), $this->actor($request)),
        ))->response()->setStatusCode(201));
    }

    public function update(int $puestoId, GuardarPuestoRequest $request, ActualizarPuesto $actualizar): JsonResponse
    {
        return $this->responder(fn () => (new PuestoDetalleResource(
            $actualizar->ejecutar(
                $puestoId,
                (int) $request->validated('version'),
                $request->datos(),
                $this->actor($request),
                $request->validated('codigo'),
            ),
        ))->response());
    }

    public function estado(int $puestoId, CambiarEstadoPuestoRequest $request, CambiarEstadoPuesto $cambiar): JsonResponse
    {
        return $this->responder(fn () => (new PuestoDetalleResource(
            $cambiar->ejecutar($puestoId, $request->estado(), $this->actor($request)),
        ))->response());
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
        } catch (PuestoNoEncontrado $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (PuestoModificado $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (PuestoConPosicionesEnUso $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['estado' => [$e->getMessage()]],
                'posiciones_en_uso' => $e->cantidad,
            ], 422);
        } catch (CodigoDePuestoNoEditable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['codigo' => [$e->getMessage()]],
            ], 422);
        } catch (NombreDePuestoDuplicado $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['nombre' => [$e->getMessage()]],
            ], 422);
        } catch (CompetenciasDePuestoInvalidas $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => array_map(fn (string $mensaje) => [$mensaje], $e->errores()),
            ], 422);
        }
    }
}
