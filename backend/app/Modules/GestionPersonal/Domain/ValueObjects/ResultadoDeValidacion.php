<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

/** Resultado de validar un archivo: válido, o inválido con un motivo (`vacio`, `no_es_pdf` o `estructura_invalida`). */
final class ResultadoDeValidacion
{
    private function __construct(public readonly bool $valido, public readonly ?string $motivo = null) {}

    public static function valido(): self
    {
        return new self(true);
    }

    public static function invalido(string $motivo): self
    {
        return new self(false, $motivo);
    }
}
