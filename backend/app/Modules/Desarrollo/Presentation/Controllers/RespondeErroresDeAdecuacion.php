<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Domain\Exceptions\ErrorDeAdecuacion;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Traduce los errores de negocio de la adecuación a respuestas HTTP seguras, con código estable y errores por campo. */
trait RespondeErroresDeAdecuacion
{
    private function tenant(Request $request): int
    {
        return (int) $request->user()->tenant_id;
    }

    private function usuario(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }

    private function esAdministrador(Request $request): bool
    {
        $usuario = $request->user();

        return method_exists($usuario, 'tokenCan') && $usuario->tokenCan('admin');
    }

    /**
     * @param  Closure(): JsonResponse  $accion
     */
    private function responder(Closure $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (ErrorDeAdecuacion $e) {
            $cuerpo = ['message' => $e->getMessage(), 'code' => $e->codigoError()] + $e->datos();
            if ($e->errores() !== []) {
                $cuerpo['errors'] = $e->errores();
            }

            return response()->json($cuerpo, $e->estadoHttp());
        }
    }
}
