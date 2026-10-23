<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ListarAccionesVinculables;
use App\Modules\Desarrollo\Application\UseCases\RegistrarAvanceDeAccion;
use App\Modules\Desarrollo\Application\UseCases\VincularAccionDePdi;
use App\Modules\Desarrollo\Presentation\Requests\AccionesVinculablesRequest;
use App\Modules\Desarrollo\Presentation\Requests\AvanceDeAccionRequest;
use App\Modules\Desarrollo\Presentation\Requests\VinculoDeAccionRequest;
use Illuminate\Http\JsonResponse;

class PdiAccionesController
{
    use RespondeErroresDePdi;

    public function avance(int $id, AvanceDeAccionRequest $request, RegistrarAvanceDeAccion $registrar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $registrar->ejecutar(
            $this->tenant($request), $this->usuario($request), $id, $request->estado(),
        )]));
    }

    public function vinculo(int $id, VinculoDeAccionRequest $request, VincularAccionDePdi $vincular): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $vincular->ejecutar(
            $this->tenant($request), $this->usuario($request), $id, $request->planAccionId(), $request->version(),
        )]));
    }

    public function vinculables(AccionesVinculablesRequest $request, ListarAccionesVinculables $listar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $listar->ejecutar(
            $this->tenant($request), $request->colaboradorId(), $request->texto(),
        )]));
    }
}
