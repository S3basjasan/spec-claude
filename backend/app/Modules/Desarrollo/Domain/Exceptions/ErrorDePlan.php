<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use RuntimeException;

/**
 * Error de negocio del plan de formación. Lleva un código estable para la interfaz y, cuando aplica, el campo
 * al que pertenece el mensaje y el estado HTTP con que debe responderse.
 */
abstract class ErrorDePlan extends RuntimeException
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
