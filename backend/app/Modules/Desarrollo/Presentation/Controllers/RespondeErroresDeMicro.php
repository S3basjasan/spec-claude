<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Domain\Exceptions\ColaboradorSinAcceso;
use App\Modules\Desarrollo\Domain\Exceptions\ErrorDeMicro;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoPublicable;
use App\Modules\Desarrollo\Domain\Exceptions\QuizInvalido;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Traduce los errores de negocio de micro-learning a respuestas HTTP seguras, con código estable y campo cuando aplica. */
trait RespondeErroresDeMicro
{
    private function esAdmin(Request $request): bool
    {
        return (bool) $request->user()->tokenCan('admin');
    }

    private function usuario(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }

    /** Las operaciones del colaborador no aplican al Administrador. */
    private function exigirColaborador(Request $request): void
    {
        if ($this->esAdmin($request)) {
            throw new ColaboradorSinAcceso;
        }
    }

    /**
     * @param  Closure(): JsonResponse  $accion
     */
    private function responder(Closure $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (ErrorDeMicro $e) {
            return $this->errorComoRespuesta($e);
        }
    }

    private function errorComoRespuesta(ErrorDeMicro $e): JsonResponse
    {
        if ($e instanceof QuizInvalido) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->codigoError(),
                'errors' => array_map(fn (string $mensaje) => [$mensaje], $e->errores()),
            ], 422);
        }

        $cuerpo = ['message' => $e->getMessage(), 'code' => $e->codigoError()];
        if ($e->campo() !== null) {
            $cuerpo['errors'] = [$e->campo() => [$e->getMessage()]];
        }
        if ($e instanceof MicroNoPublicable) {
            $cuerpo['faltantes'] = $e->faltantes;
        }

        return response()->json($cuerpo, $e->estadoHttp());
    }

    /**
     * @return array<string, int|null>
     */
    private function metaPaginacion(LengthAwarePaginator $p): array
    {
        return [
            'current_page' => $p->currentPage(),
            'per_page' => $p->perPage(),
            'total' => $p->total(),
            'last_page' => $p->lastPage(),
            'from' => $p->firstItem(),
            'to' => $p->lastItem(),
        ];
    }
}
