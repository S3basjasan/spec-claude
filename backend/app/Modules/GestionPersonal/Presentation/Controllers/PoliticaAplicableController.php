<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\UseCases\ConsultarPoliticaAplicable;
use App\Modules\GestionPersonal\Presentation\Resources\PoliticaAplicableResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Condiciones aplicables a un tipo para cualquier usuario autenticado del tenant; es una consulta informativa. */
class PoliticaAplicableController extends PoliticaBaseController
{
    public function show(Request $request, string $tipo, ConsultarPoliticaAplicable $consultar): JsonResponse
    {
        return $this->responder(function () use ($request, $tipo, $consultar) {
            $tipoDePolitica = $this->tipo($tipo);
            $resultado = $consultar->ejecutar($this->contexto($request), $tipoDePolitica);

            return (new PoliticaAplicableResource(['tipo' => $tipoDePolitica] + $resultado))->response();
        });
    }
}
