<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

/**
 * Error de negocio de los reportes de evaluación. Lleva un código estable para la interfaz, el campo al que
 * pertenece el mensaje, errores por campo cuando son varios y el estado HTTP con que debe responderse.
 */
abstract class ErrorDeReporte extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errores
     */
    public function __construct(
        string $mensaje,
        private readonly string $codigoError,
        private readonly ?string $campo = null,
        private readonly array $errores = [],
    ) {
        parent::__construct($mensaje);
    }

    public function codigoError(): string
    {
        return $this->codigoError;
    }

    public function campo(): ?string
    {
        return $this->campo;
    }

    /** @return array<string, list<string>> */
    public function errores(): array
    {
        if ($this->errores !== []) {
            return $this->errores;
        }

        return $this->campo === null ? [] : [$this->campo => [$this->getMessage()]];
    }

    public function estadoHttp(): int
    {
        return 422;
    }
}
