<?php

namespace App\Modules\Compensaciones\Presentation\Controllers;

use App\Modules\Compensaciones\Application\DTOs\CategoriaDTO;
use App\Modules\Compensaciones\Application\Services\PoliticaDeAcceso;
use App\Modules\Compensaciones\Application\UseCases\ActualizarCategoria;
use App\Modules\Compensaciones\Application\UseCases\CambiarEstadoCategoria;
use App\Modules\Compensaciones\Application\UseCases\CrearCategoria;
use App\Modules\Compensaciones\Application\UseCases\ListarCategorias;
use App\Modules\Compensaciones\Application\UseCases\ObtenerCategoria;
use App\Modules\Compensaciones\Domain\Exceptions\CategoriaModificadaPorOtro;
use App\Modules\Compensaciones\Domain\Exceptions\CategoriaNoEncontrada;
use App\Modules\Compensaciones\Domain\Exceptions\CodigoDeCategoriaEnUso;
use App\Modules\Compensaciones\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;
use App\Modules\Compensaciones\Domain\Exceptions\NombreDeCategoriaRepetido;
use App\Modules\Compensaciones\Presentation\Requests\CambiarEstadoCategoriaRequest;
use App\Modules\Compensaciones\Presentation\Requests\GuardarCategoriaRequest;
use App\Modules\Compensaciones\Presentation\Requests\ListarCategoriasRequest;
use App\Modules\Compensaciones\Presentation\Resources\CategoriaResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriaController
{
    public function __construct(private readonly PoliticaDeAcceso $acceso) {}

    public function index(ListarCategoriasRequest $request, ListarCategorias $listar): JsonResponse
    {
        $pagina = $listar->ejecutar(
            $request->validated('q'),
            $request->validated('estado'),
            $request->validated('competencia_id') === null ? null : (int) $request->validated('competencia_id'),
            (int) ($request->validated('page') ?? 1),
            $this->acceso->puedeGestionar($request->user()),
        );

        return response()->json([
            'data' => array_map(
                fn (CategoriaDTO $dto) => (new CategoriaResource($dto))->enResumen()->resolve($request),
                $pagina['items'],
            ),
            'meta' => [
                'page' => $pagina['pagina'],
                'per_page' => $pagina['porPagina'],
                'total' => $pagina['total'],
                'last_page' => $pagina['ultimaPagina'],
            ],
        ]);
    }

    public function show(Request $request, int $categoria, ObtenerCategoria $obtener): JsonResponse
    {
        return $this->responder(fn () => (new CategoriaResource($obtener->ejecutar($categoria)))->response($request));
    }

    public function store(GuardarCategoriaRequest $request, CrearCategoria $crear): JsonResponse
    {
        return $this->responder(fn () => (new CategoriaResource($crear->ejecutar($request->datos(), $this->actor($request))))
            ->response($request)
            ->setStatusCode(201));
    }

    public function update(GuardarCategoriaRequest $request, int $categoria, ActualizarCategoria $actualizar): JsonResponse
    {
        return $this->responder(fn () => (new CategoriaResource(
            $actualizar->ejecutar($categoria, $request->versionLeida(), $request->datos(), $this->actor($request)),
        ))->response($request));
    }

    public function cambiarEstado(CambiarEstadoCategoriaRequest $request, int $categoria, CambiarEstadoCategoria $cambiar): JsonResponse
    {
        return $this->responder(fn () => (new CategoriaResource(
            $cambiar->ejecutar($categoria, $request->estado(), $request->versionLeida(), $this->actor($request)),
        ))->response($request));
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
        } catch (CategoriaNoEncontrada $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (CategoriaModificadaPorOtro $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (NombreDeCategoriaRepetido $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['nombre' => [$e->getMessage()]]], 422);
        } catch (CompetenciaNoDisponible $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['competencia_id' => [$e->getMessage()]]], 422);
        } catch (DatosDeCategoriaInvalidos $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => [$e->campo => [$e->getMessage()]]], 422);
        } catch (CodigoDeCategoriaEnUso $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }
}
