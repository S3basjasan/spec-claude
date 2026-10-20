<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Domain\Exceptions\ErrorDeReporte;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Traduce los errores de negocio de los reportes a respuestas HTTP seguras, con código estable y errores por campo. */
trait RespondeErroresDeReportes
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
     * @param  Closure(): Response  $accion
     */
    private function responder(Closure $accion): Response
    {
        try {
            return $accion();
        } catch (ErrorDeReporte $e) {
            $cuerpo = ['message' => $e->getMessage(), 'code' => $e->codigoError()];
            if ($e->errores() !== []) {
                $cuerpo['errors'] = $e->errores();
            }

            return new JsonResponse($cuerpo, $e->estadoHttp());
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $extra
     */
    private function pagina(array $items, int $total, int $pagina, int $porPagina, array $extra = []): JsonResponse
    {
        $meta = ['total' => $total, 'page' => max(1, $pagina), 'per_page' => $porPagina, 'last_page' => max(1, (int) ceil($total / max(1, $porPagina)))];

        return response()->json(['data' => $items, 'meta' => $meta + $extra]);
    }
}
