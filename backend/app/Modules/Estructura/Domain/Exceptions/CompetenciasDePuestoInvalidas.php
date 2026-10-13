<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

/**
 * Una o varias competencias solicitadas para un puesto no son válidas. Cada error se asocia a la fila
 * afectada con una clave como `competencias.2.grado_requerido`.
 */
final class CompetenciasDePuestoInvalidas extends RuntimeException
{
    /**
     * @param  array<string, string>  $errores  Clave de error → mensaje.
     */
    public function __construct(private readonly array $errores)
    {
        parent::__construct(reset($errores) ?: 'Las competencias del puesto no son válidas.');
    }

    /**
     * @return array<string, string>
     */
    public function errores(): array
    {
        return $this->errores;
    }
}
