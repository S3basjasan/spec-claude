<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

/**
 * Resultado de leer una capacidad externa: con datos, no instalada o no disponible (fallo real).
 */
final class Lectura
{
    private function __construct(
        public readonly string $estado,
        public readonly mixed $datos = null,
    ) {}

    public static function disponible(mixed $datos): self
    {
        return new self('disponible', $datos);
    }

    public static function noInstalado(): self
    {
        return new self('noInstalado');
    }

    public static function noDisponible(): self
    {
        return new self('noDisponible');
    }

    public function estaDisponible(): bool
    {
        return $this->estado === 'disponible';
    }

    public function noEstaInstalado(): bool
    {
        return $this->estado === 'noInstalado';
    }

    public function fallo(): bool
    {
        return $this->estado === 'noDisponible';
    }
}
