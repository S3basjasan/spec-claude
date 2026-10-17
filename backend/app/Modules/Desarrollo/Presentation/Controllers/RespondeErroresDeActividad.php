<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Domain\Exceptions\ErrorDeActividad;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Traduce los errores de negocio de las actividades a respuestas HTTP seguras, con código estable y errores por campo. */
trait RespondeErroresDeActividad
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
        } catch (ErrorDeActividad $e) {
            $cuerpo = ['message' => $e->getMessage(), 'code' => $e->codigoError()];
            if ($e->errores() !== []) {
                $cuerpo['errors'] = $e->errores();
            }

            return response()->json($cuerpo, $e->estadoHttp());
        }
    }
}
