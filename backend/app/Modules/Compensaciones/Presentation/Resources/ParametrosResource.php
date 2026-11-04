<?php

namespace App\Modules\Compensaciones\Presentation\Resources;

use App\Modules\Compensaciones\Application\DTOs\ParametroDTO;

/** Forma JSON de la lista de parámetros (ver contracts/parametros-api.md). */
final class ParametrosResource
{
    /**
     * @param  array<int, ParametroDTO>  $parametros
     * @return array{data: array<int, array<string, mixed>>, meta: array{puntuacion_maxima_categoria: int}}
     */
    public static function coleccion(array $parametros, int $puntuacionMaximaDeCategoria): array
    {
        return ['data' => array_map(fn (ParametroDTO $p) => [
            'clave' => $p->clave,
            'valor' => $p->valor,
            'valor_programado' => $p->valorProgramado,
            'minimo' => $p->minimo,
            'maximo' => $p->maximo,
            'por_defecto' => $p->porDefecto,
            'version' => $p->version,
            'ultimo_cambio' => $p->ultimoCambio,
        ], $parametros), 'meta' => ['puntuacion_maxima_categoria' => $puntuacionMaximaDeCategoria]];
    }
}
