<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Faltan datos para publicar; `faltantes` ∈ titulo | duracion | url | competencias | preguntas. */
final class MicroNoPublicable extends ErrorDeMicro
{
    /**
     * @param  array<int, string>  $faltantes
     */
    public function __construct(public readonly array $faltantes)
    {
        parent::__construct('Faltan datos para publicar: '.implode(', ', $faltantes).'.', 'faltan_requisitos');
    }
}
