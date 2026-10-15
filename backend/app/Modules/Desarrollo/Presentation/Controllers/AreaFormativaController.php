<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\CrearArea;
use App\Modules\Desarrollo\Application\UseCases\ListarAreas;
use App\Modules\Desarrollo\Application\UseCases\RenombrarArea;
use App\Modules\Desarrollo\Domain\Exceptions\AreaNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\NombreDeAreaDuplicado;
use App\Modules\Desarrollo\Presentation\Requests\GuardarAreaRequest;
use App\Modules\Desarrollo\Presentation\Resources\AreaFormativaResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreaFormativaController
{
    public function index(ListarAreas $listar): JsonResponse
    {
        return AreaFormativaResource::collection(collect($listar->ejecutar()))->response();
    }

    public function store(GuardarAreaRequest $request, CrearArea $crear): JsonResponse
    {
        return $this->responder(fn () => (new AreaFormativaResource(
            $crear->ejecutar((string) $request->validated('nombre'), $this->actor($request)),
        ))->response()->setStatusCode(201));
    }

    public function update(int $areaId, GuardarAreaRequest $request, RenombrarArea $renombrar): JsonResponse
    {
        return $this->responder(fn () => (new AreaFormativaResource(
            $renombrar->ejecutar($areaId, (string) $request->validated('nombre'), $this->actor($request)),
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
        } catch (AreaNoEncontrada $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (NombreDeAreaDuplicado $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['nombre' => [$e->getMessage()]],
            ], 422);
        }
    }
}
