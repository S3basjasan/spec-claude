<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ObtenerMiPlanDeDesarrollo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MiPlanPdiController
{
    use RespondeErroresDePdi;

    /** La identidad sale siempre de la sesión: no hay parámetro de colaborador. */
    public function show(Request $request, ObtenerMiPlanDeDesarrollo $miPlan): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $miPlan->ejecutar($this->tenant($request), $this->usuario($request))]));
    }
}
