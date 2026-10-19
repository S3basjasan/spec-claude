<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Los datos de la evaluación de impacto no cumplen las reglas de dominio (campos obligatorios, longitudes, beneficio no negativo). */
final class ImpactoInvalido extends ErrorDeReporte
{
    /**
     * @param  array<string, list<string>>  $errores
     */
    public function __construct(array $errores)
    {
        parent::__construct('Los datos de la evaluación de impacto no son válidos.', 'validacion', null, $errores);
    }
}
