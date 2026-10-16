<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Domain\Exceptions\ErrorDePlan;
use App\Modules\Desarrollo\Domain\Exceptions\PlanIncompleto;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Traduce los errores de negocio del plan a respuestas HTTP seguras, con código estable y campo cuando aplica. */
trait RespondeErroresDePlan
{
    private function tenant(Request $request): int
    {
        return (int) $request->user()->tenant_id;
    }

    private function usuario(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }

    /**
     * @param  Closure(): JsonResponse  $accion
     */
    private function responder(Closure $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (ErrorDePlan $e) {
            $cuerpo = ['message' => $e->getMessage(), 'code' => $e->codigoError()];
            if ($e->campo() !== null) {
                $cuerpo['errors'] = [$e->campo() => [$e->getMessage()]];
            }
            if ($e instanceof PlanIncompleto) {
                $cuerpo['faltantes'] = $e->faltantes;
            }

            return response()->json($cuerpo, $e->estadoHttp());
        }
    }
}
