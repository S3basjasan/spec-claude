<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** El nivel es nivel actual u objetivo de una inscripción Activa, o conserva historial de inscripciones. */
final class NivelEnUso extends ErrorDeRuta
{
    public function __construct(string $mensaje, string $codigo = 'nivel_en_uso')
    {
        parent::__construct($mensaje, $codigo, 'niveles', ['niveles' => [$mensaje]]);
    }
}
