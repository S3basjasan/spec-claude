<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ObtenerMiRuta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MiRutaController
{
    use RespondeErroresDeRuta;

    public function show(Request $request, ObtenerMiRuta $obtener): JsonResponse
    {
        return response()->json(['data' => $obtener->ejecutar($this->tenant($request), $this->usuario($request))]);
    }
}
