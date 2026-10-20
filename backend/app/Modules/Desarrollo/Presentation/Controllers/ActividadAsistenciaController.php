<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\RegistrarAsistencia;
use App\Modules\Desarrollo\Presentation\Requests\RegistrarAsistenciaRequest;
use Illuminate\Http\JsonResponse;

class ActividadAsistenciaController
{
    use RespondeErroresDeActividad;

    public function update(int $id, RegistrarAsistenciaRequest $request, RegistrarAsistencia $registrar): JsonResponse
    {
        return $this->responder(fn () => response()->json([
            'data' => $registrar->ejecutar($this->tenant($request), $this->usuario($request), $id, $request->version(), $request->registros()),
        ]));
    }
}
