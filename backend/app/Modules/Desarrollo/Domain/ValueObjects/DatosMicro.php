<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/**
 * Contenido editable de un micro-contenido. `preguntas` es null cuando no se envían (no cambian) y, en un Quiz,
 * una lista ordenada de `{enunciado, opciones: [{texto, es_correcta}]}`.
 */
final class DatosMicro
{
    /**
     * @param  array<int, int>  $competenciaIds
     * @param  array<int, array{enunciado: string, opciones: array<int, array{texto: string, es_correcta: bool}>}>|null  $preguntas
     */
    public function __construct(
        public readonly string $titulo,
        public readonly TipoMicro $tipo,
        public readonly int $duracionMinutos,
        public readonly ?string $url,
        public readonly ?string $miniaturaUrl,
        public readonly array $competenciaIds,
        public readonly ?array $preguntas = null,
    ) {}
}
