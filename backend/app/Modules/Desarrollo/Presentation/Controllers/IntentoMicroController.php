<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\IniciarIntento;
use App\Modules\Desarrollo\Application\UseCases\ResponderPregunta;
use App\Modules\Desarrollo\Presentation\Requests\ResponderRequest;
use App\Modules\Desarrollo\Presentation\Resources\IntentoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntentoMicroController
{
    use RespondeErroresDeMicro;

    public function iniciar(int $id, Request $request, IniciarIntento $iniciar): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $iniciar) {
            $this->exigirColaborador($request);
            $resultado = $iniciar->ejecutar($id, $this->usuario($request), $request->boolean('reiniciar'));

            return (new IntentoResource($resultado))->response()->setStatusCode($resultado['nuevo'] ? 201 : 200);
        });
    }

    public function responderPregunta(int $intentoId, ResponderRequest $request, ResponderPregunta $responder): JsonResponse
    {
        return $this->responder(function () use ($intentoId, $request, $responder) {
            $this->exigirColaborador($request);
            $r = $responder->ejecutar(
                $intentoId,
                $this->usuario($request),
                (int) $request->validated('pregunta_id'),
                (int) $request->validated('opcion_id'),
            );

            return response()->json(['data' => [
                'es_correcta' => $r['es_correcta'],
                'opcion_correcta_id' => $r['opcion_correcta_id'],
                'completado_ahora' => $r['completado_ahora'],
                'intento' => [
                    'estado' => $r['estado'],
                    'respondidas' => $r['respondidas'],
                    'puntaje' => $r['puntaje'],
                    'total_preguntas' => $r['total_preguntas'],
                ],
            ]]);
        });
    }
}
