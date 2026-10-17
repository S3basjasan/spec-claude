<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ObtenerMiAprendizaje;
use App\Modules\Desarrollo\Presentation\Resources\FilaMicroResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MiAprendizajeController
{
    use RespondeErroresDeMicro;

    public function show(Request $request, ObtenerMiAprendizaje $obtener): JsonResponse
    {
        return $this->responder(function () use ($request, $obtener) {
            $this->exigirColaborador($request);
            $mi = $obtener->ejecutar($this->usuario($request));

            $asignados = array_map(function ($dto) use ($mi, $request) {
                $fila = (new FilaMicroResource($dto))->toArray($request);
                $fila['motivo'] = $mi->motivos[$dto->micro->id] ?? null;

                return $fila;
            }, $mi->asignados);

            $completados = array_map(function ($dto) use ($request) {
                $fila = (new FilaMicroResource($dto))->toArray($request);
                $fila['completado_en'] = $dto->progreso['fecha_completado'] ?? null;
                $fila['ultimo_puntaje'] = $dto->progreso['ultimo_puntaje'] ?? null;

                return $fila;
            }, $mi->completados);

            $cuerpo = ['resumen' => $mi->resumen, 'asignados' => $asignados, 'completados' => $completados];
            if ($mi->avisos !== []) {
                $cuerpo['avisos'] = $mi->avisos;
            }

            return response()->json(['data' => $cuerpo]);
        });
    }
}
