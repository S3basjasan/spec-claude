<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Datos de la ruta o de sus niveles que no cumplen las reglas, con errores por campo. */
final class RutaInvalida extends ErrorDeRuta
{
    /** @param array<string, list<string>> $errores */
    public function __construct(string $mensaje, array $errores = [], string $codigo = 'ruta_invalida')
    {
        parent::__construct($mensaje, $codigo, null, $errores);
    }

    public static function enCampo(string $campo, string $mensaje, string $codigo = 'ruta_invalida'): self
    {
        return new self($mensaje, [$campo => [$mensaje]], $codigo);
    }
}
