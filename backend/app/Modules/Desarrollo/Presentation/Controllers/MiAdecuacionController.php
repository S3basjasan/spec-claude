<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ObtenerMiAdecuacion;
use App\Modules\Desarrollo\Presentation\Requests\MiAdecuacionRequest;
use Illuminate\Http\JsonResponse;

class MiAdecuacionController
{
    use RespondeErroresDeAdecuacion;

    public function show(MiAdecuacionRequest $request, ObtenerMiAdecuacion $obtener): JsonResponse
    {
        return response()->json(['data' => $obtener->ejecutar($this->tenant($request), $this->usuario($request), $request->puestoObjetivoId())]);
    }
}
