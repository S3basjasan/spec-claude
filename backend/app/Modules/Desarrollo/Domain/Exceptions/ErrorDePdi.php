<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

/**
 * Error de negocio del plan de desarrollo individual. Lleva un código estable para la interfaz, el campo al que
 * pertenece el mensaje, errores por campo cuando son varios, datos adicionales y el estado HTTP.
 */
abstract class ErrorDePdi extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errores
     * @param  array<string, mixed>  $datos
     */
    public function __construct(
        string $mensaje,
        private readonly string $codigoError,
        private readonly ?string $campo = null,
        private readonly array $errores = [],
        private readonly array $datos = [],
    ) {
        parent::__construct($mensaje);
    }

    public function codigoError(): string
    {
        return $this->codigoError;
    }

    /** @return array<string, list<string>> */
    public function errores(): array
    {
        if ($this->errores !== []) {
            return $this->errores;
        }

        return $this->campo === null ? [] : [$this->campo => [$this->getMessage()]];
    }

    /** @return array<string, mixed> */
    public function datos(): array
    {
        return $this->datos;
    }

    public function estadoHttp(): int
    {
        return 422;
    }
}
