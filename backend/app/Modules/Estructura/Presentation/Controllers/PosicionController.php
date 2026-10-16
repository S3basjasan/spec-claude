<?php

namespace App\Modules\Estructura\Presentation\Controllers;

use App\Modules\Estructura\Application\UseCases\ActualizarPosicion;
use App\Modules\Estructura\Application\UseCases\CrearPosicion;
use App\Modules\Estructura\Application\UseCases\ListarOpcionesDePosicion;
use App\Modules\Estructura\Application\UseCases\ListarPosiciones;
use App\Modules\Estructura\Application\UseCases\ObtenerPosicion;
use App\Modules\Estructura\Application\UseCases\SuprimirPosicion;
use App\Modules\Estructura\Domain\Exceptions\PosicionInvalida;
use App\Modules\Estructura\Domain\Exceptions\PosicionModificada;
use App\Modules\Estructura\Domain\Exceptions\PosicionNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\PosicionNoSuprimible;
use App\Modules\Estructura\Presentation\Requests\GuardarPosicionRequest;
use App\Modules\Estructura\Presentation\Requests\ListarPosicionesRequest;
use App\Modules\Estructura\Presentation\Requests\OpcionesDePosicionRequest;
use App\Modules\Estructura\Presentation\Resources\OpcionesDePosicionResource;
use App\Modules\Estructura\Presentation\Resources\PosicionDetalleResource;
use App\Modules\Estructura\Presentation\Resources\PosicionResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosicionController
{
    public function index(ListarPosicionesRequest $request, ListarPosiciones $listar): JsonResponse
    {
        // La capacidad `admin` del token decide si se pueden ver las posiciones Suprimidas.
        $listado = $listar->ejecutar($request->filtro($this->esAdministrador($request)));

        return PosicionResource::collection($listado->items)
            ->additional(['meta' => [
                'current_page' => $listado->pagina,
                'per_page' => $listado->porPagina,
                'total' => $listado->total,
                'last_page' => $listado->ultimaPagina(),
                'from' => $listado->desde(),
                'to' => $listado->hasta(),
            ]])
            ->response();
    }

    public function opciones(OpcionesDePosicionRequest $request, ListarOpcionesDePosicion $listar): JsonResponse
    {
        return (new OpcionesDePosicionResource($listar->ejecutar($request->excluir())))->response();
    }

    public function show(Request $request, int $posicionId, ObtenerPosicion $obtener): JsonResponse
    {
        return $this->responder(fn () => (new PosicionDetalleResource(
            $obtener->ejecutar($posicionId, $this->esAdministrador($request)),
        ))->response());
    }

    public function store(GuardarPosicionRequest $request, CrearPosicion $crear): JsonResponse
    {
        return $this->responder(fn () => (new PosicionDetalleResource(
            $crear->ejecutar($request->datos(), $this->actor($request)),
        ))->response()->setStatusCode(201));
    }

    public function update(int $posicionId, GuardarPosicionRequest $request, ActualizarPosicion $actualizar): JsonResponse
    {
        return $this->responder(fn () => (new PosicionDetalleResource(
            $actualizar->ejecutar($posicionId, (int) $request->validated('version'), $request->datos(), $this->actor($request)),
        ))->response());
    }

    public function suprimir(Request $request, int $posicionId, SuprimirPosicion $suprimir): JsonResponse
    {
        return $this->responder(fn () => (new PosicionDetalleResource(
            $suprimir->ejecutar($posicionId, $this->actor($request)),
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
        } catch (PosicionNoEncontrada $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (PosicionModificada $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (PosicionInvalida $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => array_map(fn (string $mensaje) => [$mensaje], $e->errores),
            ], 422);
        } catch (PosicionNoSuprimible $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['estado' => [$e->getMessage()]],
                'motivo' => $e->motivo,
                'subordinadas_vigentes' => $e->subordinadasVigentes,
            ], 422);
        }
    }
}
