<?php

namespace App\Modules\Estructura\Presentation\Controllers;

use App\Modules\Estructura\Application\UseCases\ActualizarComportamientos;
use App\Modules\Estructura\Application\UseCases\CrearComportamientos;
use App\Modules\Estructura\Application\UseCases\ListarCompetenciasDisponibles;
use App\Modules\Estructura\Application\UseCases\ListarComportamientos;
use App\Modules\Estructura\Application\UseCases\ObtenerComportamientos;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTieneRegistro;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Domain\Exceptions\RegistroNoEncontrado;
use App\Modules\Estructura\Presentation\Requests\GuardarComportamientosRequest;
use App\Modules\Estructura\Presentation\Requests\ListarComportamientosRequest;
use App\Modules\Estructura\Presentation\Resources\CompetenciaDisponibleResource;
use App\Modules\Estructura\Presentation\Resources\RegistroComportamientosDetalleResource;
use App\Modules\Estructura\Presentation\Resources\RegistroComportamientosResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ComportamientoController
{
    public function index(ListarComportamientosRequest $request, ListarComportamientos $listar): JsonResponse
    {
        // La capacidad `admin` del token decide si se pueden ver los registros de competencias inactivas.
        $listado = $listar->ejecutar($request->filtro($request->user()->tokenCan('admin')));

        $paginador = (new LengthAwarePaginator(
            $listado->items,
            $listado->total,
            $listado->porPagina,
            $listado->pagina,
            ['path' => $request->url()],
        ))->appends($request->query());

        return RegistroComportamientosResource::collection($paginador)->response();
    }

    public function disponibles(ListarCompetenciasDisponibles $listar): JsonResponse
    {
        return CompetenciaDisponibleResource::collection(collect($listar->ejecutar()))->response();
    }

    public function show(int $competenciaId, ObtenerComportamientos $obtener): JsonResponse
    {
        return $this->responder(fn () => (new RegistroComportamientosDetalleResource($obtener->ejecutar($competenciaId)))->response());
    }

    public function store(GuardarComportamientosRequest $request, CrearComportamientos $crear): JsonResponse
    {
        return $this->responder(fn () => (new RegistroComportamientosDetalleResource(
            $crear->ejecutar((int) $request->validated('competencia_id'), $request->comportamientos(), $this->actor($request)),
        ))->response()->setStatusCode(201));
    }

    public function update(int $competenciaId, GuardarComportamientosRequest $request, ActualizarComportamientos $actualizar): JsonResponse
    {
        $enviada = $request->validated('competencia_id');
        if ($enviada !== null && (int) $enviada !== $competenciaId) {
            return response()->json([
                'message' => 'La competencia de un registro no se puede cambiar.',
                'errors' => ['competencia_id' => ['La competencia de un registro no se puede cambiar.']],
            ], 422);
        }

        return $this->responder(fn () => (new RegistroComportamientosDetalleResource(
            $actualizar->ejecutar($competenciaId, (int) $request->validated('version'), $request->comportamientos(), $this->actor($request)),
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
        } catch (RegistroNoEncontrado $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (RegistroModificado $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (CompetenciaNoDisponible|CompetenciaYaTieneRegistro $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['competencia_id' => [$e->getMessage()]],
            ], 422);
        }
    }
}
