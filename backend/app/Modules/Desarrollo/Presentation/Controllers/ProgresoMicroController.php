<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\CompletarMicro;
use App\Modules\Desarrollo\Application\UseCases\GuardarAvanceMicro;
use App\Modules\Desarrollo\Presentation\Requests\GuardarAvanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgresoMicroController
{
    use RespondeErroresDeMicro;

    public function avance(int $id, GuardarAvanceRequest $request, GuardarAvanceMicro $guardar): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $guardar) {
            $this->exigirColaborador($request);
            $p = $guardar->ejecutar($id, $this->usuario($request), $request->posicion());

            return response()->json(['data' => [
                'estado' => $p->estado->value,
                'posicion_segundos' => $p->posicionSegundos,
                'fecha_inicio' => $p->fechaInicio,
            ]]);
        });
    }

    public function completar(int $id, Request $request, CompletarMicro $completar): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $completar) {
            $this->exigirColaborador($request);
            $p = $completar->ejecutar($id, $this->usuario($request));

            return response()->json(['data' => [
                'estado' => $p->estado->value,
                'fecha_completado' => $p->fechaCompletado,
            ]]);
        });
    }
}
