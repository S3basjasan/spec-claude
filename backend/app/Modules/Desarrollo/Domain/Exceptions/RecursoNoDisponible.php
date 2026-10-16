<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

/**
 * Uno o varios recursos solicitados no son válidos (repetidos, inexistentes o archivados). Cada error se
 * asocia a su posición con una clave como `curso_ids.1`.
 */
final class RecursoNoDisponible extends RuntimeException
{
    /**
     * @param  array<string, string>  $errores  Clave de error → mensaje.
     */
    public function __construct(private readonly array $errores)
    {
        parent::__construct(reset($errores) ?: 'Alguno de los recursos no está disponible.');
    }

    /**
     * @return array<string, string>
     */
    public function errores(): array
    {
        return $this->errores;
    }
}
