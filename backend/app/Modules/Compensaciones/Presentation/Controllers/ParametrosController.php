<?php

namespace App\Modules\Compensaciones\Presentation\Controllers;

use App\Modules\Compensaciones\Application\Services\PoliticaDeAcceso;
use App\Modules\Compensaciones\Application\UseCases\ConsultarParametros;
use App\Modules\Compensaciones\Application\UseCases\GuardarParametros;
use App\Modules\Compensaciones\Domain\Exceptions\ParametrosModificadosPorOtro;
use App\Modules\Compensaciones\Domain\Exceptions\ValorDeParametroInvalido;
use App\Modules\Compensaciones\Presentation\Requests\GuardarParametrosRequest;
use App\Modules\Compensaciones\Presentation\Resources\ParametrosResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParametrosController
{
    public function __construct(private readonly PoliticaDeAcceso $acceso) {}

    public function index(Request $request, ConsultarParametros $consultar): JsonResponse
    {
        if (! $this->puedeGestionar($request)) {
            return $this->sinPermiso();
        }

        return response()->json(ParametrosResource::coleccion($consultar->ejecutar()));
    }

    public function update(GuardarParametrosRequest $request, GuardarParametros $guardar, ConsultarParametros $consultar): JsonResponse
    {
        if (! $this->puedeGestionar($request)) {
            return $this->sinPermiso();
        }

        try {
            $guardar->ejecutar($request->cambios(), (int) $request->user()->getAuthIdentifier());
        } catch (ParametrosModificadosPorOtro $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (ValorDeParametroInvalido $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ["cambios.{$e->indice}.valor" => [$e->getMessage()]],
            ], 422);
        }

        return response()->json(ParametrosResource::coleccion($consultar->ejecutar()));
    }

    private function puedeGestionar(Request $request): bool
    {
        return $this->acceso->puede($request->user(), PoliticaDeAcceso::PARAMETROS_GESTIONAR);
    }

    private function sinPermiso(): JsonResponse
    {
        return response()->json(['message' => 'No tienes permisos para realizar esta acción.'], 403);
    }
}
