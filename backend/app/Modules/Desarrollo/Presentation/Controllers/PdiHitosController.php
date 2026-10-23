<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\RegistrarAvanceDeHito;
use App\Modules\Desarrollo\Presentation\Requests\AvanceDeHitoRequest;
use Illuminate\Http\JsonResponse;

class PdiHitosController
{
    use RespondeErroresDePdi;

    public function update(int $id, AvanceDeHitoRequest $request, RegistrarAvanceDeHito $registrar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $registrar->ejecutar(
            $this->tenant($request), $this->usuario($request), $id, $request->estado(), $request->observaciones(),
        )]));
    }
}
