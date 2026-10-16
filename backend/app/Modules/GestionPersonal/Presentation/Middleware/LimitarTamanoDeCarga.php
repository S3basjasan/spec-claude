<?php

namespace App\Modules\GestionPersonal\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convierte en 413 el caso en que PHP o el proxy descartan un cuerpo mayor que el permitido: nunca se presenta como
 * éxito ni como un error genérico de validación. El límite exacto del archivo lo decide el caso de uso.
 */
class LimitarTamanoDeCarga
{
    public function handle(Request $request, Closure $next): Response
    {
        $declarado = (int) $request->server('CONTENT_LENGTH', 0);
        $limite = $this->bytes((string) ini_get('post_max_size'));
        $archivo = $request->file('archivo');
        $error = $archivo?->getError();

        $excedePost = $limite > 0 && $declarado > $limite;
        $excedeIni = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);

        if ($excedePost || $excedeIni) {
            return response()->json([
                'message' => 'El archivo supera el tamaño máximo de 10 MB.',
                'code' => 'ARCHIVO_DEMASIADO_GRANDE',
            ], 413);
        }

        return $next($request);
    }

    private function bytes(string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '' || $valor === '0' || $valor === '-1') {
            return 0;
        }
        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }
}
