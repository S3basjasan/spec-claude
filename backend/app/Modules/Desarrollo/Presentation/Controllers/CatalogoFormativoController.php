<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ActualizarConfiguracionFormativa;
use App\Modules\Desarrollo\Application\UseCases\ListarCatalogo;
use App\Modules\Desarrollo\Application\UseCases\ListarHistorial;
use App\Modules\Desarrollo\Application\UseCases\ListarRecomendados;
use App\Modules\Desarrollo\Application\UseCases\ListarRecursosDisponibles;
use App\Modules\Desarrollo\Application\UseCases\ObtenerCobertura;
use App\Modules\Desarrollo\Application\UseCases\ObtenerDetalleFormativo;
use App\Modules\Desarrollo\Domain\Exceptions\AreaNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\CompetenciaInactiva;
use App\Modules\Desarrollo\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\ConfiguracionModificada;
use App\Modules\Desarrollo\Domain\Exceptions\RecursoNoDisponible;
use App\Modules\Desarrollo\Presentation\Requests\GuardarConfiguracionRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarCatalogoRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarHistorialRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarRecursosDisponiblesRequest;
use App\Modules\Desarrollo\Presentation\Resources\DetalleFormativoResource;
use App\Modules\Desarrollo\Presentation\Resources\FilaCatalogoResource;
use App\Modules\Desarrollo\Presentation\Resources\RecomendadoResource;
use App\Modules\Desarrollo\Presentation\Resources\RecursoResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CatalogoFormativoController
{
    public function index(ListarCatalogoRequest $request, ListarCatalogo $listar): JsonResponse
    {
        // La capacidad `admin` del token decide la visibilidad (inactivas, nivel propio); el rol es excluyente.
        $listado = $listar->ejecutar($request->filtro($this->esAdmin($request), $this->actor($request)));

        $paginador = (new LengthAwarePaginator(
            $listado->items,
            $listado->total,
            $listado->porPagina,
            $listado->pagina,
            ['path' => $request->url()],
        ))->appends($request->query());

        return FilaCatalogoResource::collection($paginador)->response();
    }

    public function show(int $competenciaId, Request $request, ObtenerDetalleFormativo $obtener): JsonResponse
    {
        return $this->responder(fn () => (new DetalleFormativoResource(
            $obtener->ejecutar($competenciaId, $this->esAdmin($request), $this->actor($request)),
        ))->response());
    }

    public function cobertura(ObtenerCobertura $obtener): JsonResponse
    {
        $c = $obtener->ejecutar();

        return response()->json(['data' => [
            'activas_totales' => $c->activasTotales,
            'activas_cubiertas' => $c->activasCubiertas,
            'porcentaje' => $c->porcentaje,
        ]]);
    }

    public function recomendados(Request $request, ListarRecomendados $listar): JsonResponse
    {
        // Solo el colaborador tiene recomendados; el administrador recibe siempre una lista vacía.
        $lista = $this->esAdmin($request) ? [] : $listar->ejecutar($this->actor($request));

        return RecomendadoResource::collection(collect($lista))->response();
    }

    public function recursosDisponibles(ListarRecursosDisponiblesRequest $request, ListarRecursosDisponibles $listar): JsonResponse
    {
        $competencia = $request->filled('competencia_id') ? (int) $request->validated('competencia_id') : null;

        return RecursoResource::collection(collect(
            $listar->ejecutar($request->tipoRecurso(), $request->validated('q'), $competencia),
        ))->response();
    }

    public function update(int $competenciaId, GuardarConfiguracionRequest $request, ActualizarConfiguracionFormativa $actualizar): JsonResponse
    {
        return $this->responder(fn () => (new DetalleFormativoResource(
            $actualizar->ejecutar($competenciaId, (int) $request->validated('version'), $request->datos(), $this->actor($request)),
        ))->response());
    }

    public function historial(int $competenciaId, ListarHistorialRequest $request, ListarHistorial $listar): JsonResponse
    {
        return $this->responder(function () use ($competenciaId, $request, $listar) {
            $historial = $listar->ejecutar($competenciaId, $request->pagina(), $request->porPagina());

            $paginador = (new LengthAwarePaginator(
                $historial->items,
                $historial->total,
                $historial->porPagina,
                $historial->pagina,
                ['path' => $request->url()],
            ))->appends($request->query());

            return response()->json([
                'data' => $paginador->items(),
                'links' => $this->enlaces($paginador),
                'meta' => $this->meta($paginador),
            ]);
        });
    }

    private function esAdmin(Request $request): bool
    {
        return (bool) $request->user()->tokenCan('admin');
    }

    private function actor(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }

    /**
     * @return array<string, string|null>
     */
    private function enlaces(LengthAwarePaginator $p): array
    {
        return ['first' => $p->url(1), 'last' => $p->url($p->lastPage()), 'prev' => $p->previousPageUrl(), 'next' => $p->nextPageUrl()];
    }

    /**
     * @return array<string, int|null>
     */
    private function meta(LengthAwarePaginator $p): array
    {
        return [
            'current_page' => $p->currentPage(),
            'per_page' => $p->perPage(),
            'total' => $p->total(),
            'last_page' => $p->lastPage(),
            'from' => $p->firstItem(),
            'to' => $p->lastItem(),
        ];
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
        } catch (ConfiguracionModificada $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (CompetenciaInactiva $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['competencia' => [$e->getMessage()]],
            ], 422);
        } catch (AreaNoEncontrada $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['area_formativa_id' => [$e->getMessage()]],
            ], 422);
        } catch (RecursoNoDisponible $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => array_map(fn (string $mensaje) => [$mensaje], $e->errores()),
            ], 422);
        }
    }
}
