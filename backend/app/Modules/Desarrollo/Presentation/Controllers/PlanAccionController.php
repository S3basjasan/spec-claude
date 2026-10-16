<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\CambiarParticipantes;
use App\Modules\Desarrollo\Application\UseCases\CancelarAccion;
use App\Modules\Desarrollo\Application\UseCases\ListarAccionesDePlanes;
use App\Modules\Desarrollo\Presentation\Requests\CambiarParticipantesRequest;
use App\Modules\Desarrollo\Presentation\Requests\CancelarAccionRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarAccionesRequest;
use Illuminate\Http\JsonResponse;

class PlanAccionController
{
    use RespondeErroresDePlan;

    public function index(ListarAccionesRequest $request, ListarAccionesDePlanes $listar): JsonResponse
    {
        return response()->json($listar->ejecutar($this->tenant($request), $this->usuario($request), $request->filtro()));
    }

    public function participantes(int $id, CambiarParticipantesRequest $request, CambiarParticipantes $cambiar): JsonResponse
    {
        return $this->responder(fn () => response()->json([
            'data' => $cambiar->ejecutar($this->tenant($request), $this->usuario($request), $id, $request->colaboradorIds()),
        ]));
    }

    public function cancelar(int $id, CancelarAccionRequest $request, CancelarAccion $cancelar): JsonResponse
    {
        return $this->responder(fn () => response()->json([
            'data' => $cancelar->ejecutar($this->tenant($request), $this->usuario($request), $id, $request->motivo()),
        ]));
    }
}
