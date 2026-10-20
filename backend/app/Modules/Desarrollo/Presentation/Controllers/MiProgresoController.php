<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ObtenerMiProgreso;
use App\Modules\Desarrollo\Application\UseCases\ResponderEncuesta;
use App\Modules\Desarrollo\Application\UseCases\ResponderSeguimiento;
use App\Modules\Desarrollo\Presentation\Requests\ResponderEncuestaRequest;
use App\Modules\Desarrollo\Presentation\Requests\ResponderSeguimientoRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MiProgresoController
{
    use RespondeErroresDeReportes;

    public function show(Request $request, ObtenerMiProgreso $obtener): Response
    {
        return $this->responder(fn () => response()->json(['data' => $obtener->ejecutar($this->usuario($request))]));
    }

    public function responderEncuesta(ResponderEncuestaRequest $request, ResponderEncuesta $responder): Response
    {
        return $this->responder(function () use ($request, $responder) {
            $v = $request->validated();

            return response()->json(['data' => $responder->ejecutar($this->usuario($request), (int) $v['actividad_id'], (int) $v['calificacion'], $v['comentario'] ?? null)], 201);
        });
    }

    public function responderSeguimiento(ResponderSeguimientoRequest $request, ResponderSeguimiento $responder): Response
    {
        return $this->responder(function () use ($request, $responder) {
            $v = $request->validated();

            return response()->json(['data' => $responder->ejecutar(
                $this->usuario($request), (int) $v['actividad_id'], (int) $v['hito'], (int) $v['aplicacion'], (int) $v['apoyo'], $v['comentario'] ?? null,
            )], 201);
        });
    }
}
