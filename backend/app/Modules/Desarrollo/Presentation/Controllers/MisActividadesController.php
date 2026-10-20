<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ObtenerMisActividades;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MisActividadesController
{
    use RespondeErroresDeActividad;

    public function show(Request $request, ObtenerMisActividades $obtener): JsonResponse
    {
        return response()->json(['data' => $obtener->ejecutar($this->usuario($request))]);
    }
}
