<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Operación sobre una ruta que su estado o sus reglas no permiten (publicar sin niveles, archivar sin confirmar…). */
final class RutaNoPermitida extends ErrorDeRuta
{
    /** @param array<string, mixed> $datos */
    public function __construct(string $mensaje, string $codigo, array $datos = [])
    {
        parent::__construct($mensaje, $codigo, null, [], $datos);
    }
}
