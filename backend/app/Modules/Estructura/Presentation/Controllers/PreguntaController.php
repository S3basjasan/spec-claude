<?php

namespace App\Modules\Estructura\Presentation\Controllers;

use App\Modules\Estructura\Application\UseCases\ActualizarPreguntas;
use App\Modules\Estructura\Application\UseCases\CrearPreguntas;
use App\Modules\Estructura\Application\UseCases\ListarCompetenciasDisponiblesPreguntas;
use App\Modules\Estructura\Application\UseCases\ListarPreguntas;
use App\Modules\Estructura\Application\UseCases\ObtenerPreguntas;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTienePreguntas;
use App\Modules\Estructura\Domain\Exceptions\PreguntasNoEncontradas;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Presentation\Requests\GuardarPreguntasRequest;
use App\Modules\Estructura\Presentation\Requests\ListarPreguntasRequest;
use App\Modules\Estructura\Presentation\Resources\CompetenciaDisponibleResource;
use App\Modules\Estructura\Presentation\Resources\RegistroPreguntasDetalleResource;
use App\Modules\Estructura\Presentation\Resources\RegistroPreguntasResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PreguntaController
{
    public function index(ListarPreguntasRequest $request, ListarPreguntas $listar): JsonResponse
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

        return RegistroPreguntasResource::collection($paginador)->response();
    }

    public function disponibles(ListarCompetenciasDisponiblesPreguntas $listar): JsonResponse
    {
        return CompetenciaDisponibleResource::collection(collect($listar->ejecutar()))->response();
    }

    public function show(int $competenciaId, ObtenerPreguntas $obtener): JsonResponse
    {
        return $this->responder(fn () => (new RegistroPreguntasDetalleResource($obtener->ejecutar($competenciaId)))->response());
    }

    public function store(GuardarPreguntasRequest $request, CrearPreguntas $crear): JsonResponse
    {
        return $this->responder(fn () => (new RegistroPreguntasDetalleResource(
            $crear->ejecutar((int) $request->validated('competencia_id'), $request->preguntas(), $this->actor($request)),
        ))->response()->setStatusCode(201));
    }

    public function update(int $competenciaId, GuardarPreguntasRequest $request, ActualizarPreguntas $actualizar): JsonResponse
    {
        $enviada = $request->validated('competencia_id');
        if ($enviada !== null && (int) $enviada !== $competenciaId) {
            return response()->json([
                'message' => 'La competencia de un registro no se puede cambiar.',
                'errors' => ['competencia_id' => ['La competencia de un registro no se puede cambiar.']],
            ], 422);
        }

        return $this->responder(fn () => (new RegistroPreguntasDetalleResource(
            $actualizar->ejecutar($competenciaId, (int) $request->validated('version'), $request->preguntas(), $this->actor($request)),
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
        } catch (PreguntasNoEncontradas $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (RegistroModificado $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (CompetenciaNoDisponible|CompetenciaYaTienePreguntas $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['competencia_id' => [$e->getMessage()]],
            ], 422);
        }
    }
}
