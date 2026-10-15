<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Configuración formativa solicitada para una competencia (entrada del caso de uso y del repositorio). */
final class DatosConfiguracion
{
    /**
     * @param  array<int, int>  $cursoIds
     * @param  array<int, int>  $microcontenidoIds
     */
    public function __construct(
        public readonly ?int $areaFormativaId,
        public readonly array $cursoIds,
        public readonly array $microcontenidoIds,
    ) {}
}
