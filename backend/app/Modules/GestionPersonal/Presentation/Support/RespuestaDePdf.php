<?php

namespace App\Modules\GestionPersonal\Presentation\Support;

use App\Modules\GestionPersonal\Application\DTO\ContenidoDeDocumentoDTO;
use Symfony\Component\HttpFoundation\Response;

/**
 * Respuestas de PDF con las cabeceras seguras comunes: nunca cacheables ni interpretables, con nombre fijo.
 */
final class RespuestaDePdf
{
    /** @return array<string, string> */
    public static function cabeceras(string $nombreDeArchivo, int $tamano, bool $descarga): array
    {
        $nombre = preg_replace('/[^A-Za-z0-9_.-]/', '_', $nombreDeArchivo) ?: 'documento.pdf';

        return [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($descarga ? 'attachment' : 'inline').'; filename="'.$nombre.'"',
            'Content-Length' => (string) $tamano,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /** Entrega en streaming; el flujo ya está abierto, así que un fallo del almacén ocurrió antes de llegar aquí. */
    public static function flujo(ContenidoDeDocumentoDTO $contenido, bool $descarga): Response
    {
        return response()->stream(function () use ($contenido): void {
            fpassthru($contenido->flujo);
            if (is_resource($contenido->flujo)) {
                fclose($contenido->flujo);
            }
        }, 200, self::cabeceras($contenido->nombreDeArchivo, $contenido->tamano, $descarga));
    }

    /** Entrega un PDF ya generado completo en memoria. */
    public static function bytes(string $pdf, string $nombreDeArchivo): Response
    {
        return response($pdf, 200, self::cabeceras($nombreDeArchivo, strlen($pdf), true));
    }
}
