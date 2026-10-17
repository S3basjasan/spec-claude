<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

/**
 * Error de negocio de micro-learning. Lleva un código estable para la interfaz, el campo al que pertenece el
 * mensaje cuando aplica y el estado HTTP con que debe responderse (422 por defecto).
 */
abstract class ErrorDeMicro extends RuntimeException
{
    public function __construct(string $mensaje, private readonly string $codigoError, private readonly ?string $campo = null)
    {
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

    public function estadoHttp(): int
    {
        return 422;
    }
}
