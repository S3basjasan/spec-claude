<?php

namespace App\Modules\Estructura\Presentation\Controllers;

use App\Modules\Estructura\Application\UseCases\ActualizarCompetencia;
use App\Modules\Estructura\Application\UseCases\ActualizarComportamientos;
use App\Modules\Estructura\Application\UseCases\ActualizarPreguntas;
use App\Modules\Estructura\Application\UseCases\AplicarEscala;
use App\Modules\Estructura\Application\UseCases\CambiarEstadoCompetencia;
use App\Modules\Estructura\Application\UseCases\CrearCompetencia;
use App\Modules\Estructura\Application\UseCases\ListarCompetencias;
use App\Modules\Estructura\Application\UseCases\ObtenerCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CategoriasInvalidas;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaIncompleta;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Exceptions\EscalaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\EscalaSinCambio;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use App\Modules\Estructura\Presentation\Requests\AplicarEscalaRequest;
use App\Modules\Estructura\Presentation\Requests\CambiarEstadoRequest;
use App\Modules\Estructura\Presentation\Requests\GuardarCompetenciaRequest;
use App\Modules\Estructura\Presentation\Requests\GuardarComportamientosRequest;
use App\Modules\Estructura\Presentation\Requests\GuardarPreguntasRequest;
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

    public function show(Request $request, int $id, ObtenerCompetencia $obtener): JsonResponse
    {
        return $this->responder(fn () => (new CompetenciaDetalleResource(
            $obtener->ejecutar($id, $request->user()->tokenCan('admin')),
        ))->response());
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

    public function actualizarComportamientos(int $id, GuardarComportamientosRequest $request, ActualizarComportamientos $actualizar): JsonResponse
    {
        return $this->responder(fn () => (new CompetenciaDetalleResource(
            $actualizar->ejecutar($id, $request->version(), $request->comportamientos(), $this->actor($request)),
        ))->response());
    }

    public function actualizarPreguntas(int $id, GuardarPreguntasRequest $request, ActualizarPreguntas $actualizar): JsonResponse
    {
        return $this->responder(fn () => (new CompetenciaDetalleResource(
            $actualizar->ejecutar($id, $request->version(), $request->preguntas(), $this->actor($request)),
        ))->response());
    }

    public function aplicarEscala(int $id, AplicarEscalaRequest $request, AplicarEscala $aplicar): JsonResponse
    {
        return $this->responder(fn () => (new CompetenciaDetalleResource(
            $aplicar->ejecutar($id, $request->escalaId(), $this->actor($request)),
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
            return $this->rechazo('nombre', $e->getMessage());
        } catch (CategoriasInvalidas $e) {
            return $this->rechazo('categorias', $e->getMessage());
        } catch (EscalaNoEncontrada|EscalaSinCambio $e) {
            return $this->rechazo('tipo_grado_id', $e->getMessage());
        } catch (EstadoSinCambio|CompetenciaIncompleta $e) {
            return $this->rechazo('estado', $e->getMessage());
        }
    }

    private function rechazo(string $campo, string $mensaje): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'errors' => [$campo => [$mensaje]],
        ], 422);
    }
}
