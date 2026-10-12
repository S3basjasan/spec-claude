<?php

namespace App\Modules\Estructura\Presentation\Controllers;

use App\Modules\Estructura\Application\UseCases\ActualizarCompetencia;
use App\Modules\Estructura\Application\UseCases\CambiarEstadoCompetencia;
use App\Modules\Estructura\Application\UseCases\CrearCompetencia;
use App\Modules\Estructura\Application\UseCases\ListarCompetencias;
use App\Modules\Estructura\Application\UseCases\ObtenerCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Presentation\Requests\CambiarEstadoRequest;
use App\Modules\Estructura\Presentation\Requests\GuardarCompetenciaRequest;
use App\Modules\Estructura\Presentation\Requests\ListarCompetenciasRequest;
use App\Modules\Estructura\Presentation\Resources\CompetenciaDetalleResource;
use App\Modules\Estructura\Presentation\Resources\CompetenciaResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CompetenciaController
{
    public function index(ListarCompetenciasRequest $request, ListarCompetencias $listar): JsonResponse
    {
        // La capacidad `admin` del token decide si se pueden ver las inactivas.
        $listado = $listar->ejecutar($request->filtro($request->user()->tokenCan('admin')));

        $paginador = (new LengthAwarePaginator(
            $listado->items,
            $listado->total,
            $listado->porPagina,
            $listado->pagina,
            ['path' => $request->url()],
        ))->appends($request->query());

        return CompetenciaResource::collection($paginador)->response();
    }

    public function show(int $id, ObtenerCompetencia $obtener): JsonResponse
    {
        return $this->responder(fn () => (new CompetenciaDetalleResource($obtener->ejecutar($id)))->response());
    }

    public function store(GuardarCompetenciaRequest $request, CrearCompetencia $crear): JsonResponse
    {
        return $this->responder(fn () => (new CompetenciaDetalleResource($crear->ejecutar($request->datos(), $this->actor($request))))
            ->response()
            ->setStatusCode(201));
    }

    public function update(int $id, GuardarCompetenciaRequest $request, ActualizarCompetencia $actualizar): JsonResponse
    {
        return $this->responder(fn () => (new CompetenciaDetalleResource(
            $actualizar->ejecutar($id, (int) $request->validated('version'), $request->datos(), $this->actor($request)),
        ))->response());
    }

    public function cambiarEstado(int $id, CambiarEstadoRequest $request, CambiarEstadoCompetencia $cambiar): JsonResponse
    {
        return $this->responder(fn () => (new CompetenciaDetalleResource(
            $cambiar->ejecutar($id, $request->estado(), $this->actor($request)),
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
        } catch (CompetenciaNoEncontrada $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ConflictoDeVersion $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (NombreDuplicado $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['nombre' => [$e->getMessage()]],
            ], 422);
        } catch (EstadoSinCambio $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['estado' => [$e->getMessage()]],
            ], 422);
        }
    }
}
