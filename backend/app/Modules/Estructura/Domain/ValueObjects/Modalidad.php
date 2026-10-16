<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Modalidad con la que se ocupa (o se cubrirá) una posición. En la base `Subrogacion` va sin tilde.
 */
enum Modalidad: string
{
    case Nombramiento = 'Nombramiento';
    case Contrato = 'Contrato';
    case Encargo = 'Encargo';
    case Subrogacion = 'Subrogacion';

    public function etiqueta(): string
    {
        return $this === self::Subrogacion ? 'Subrogación' : $this->value;
    }
}
