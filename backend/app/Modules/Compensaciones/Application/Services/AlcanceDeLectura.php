<?php

namespace App\Modules\Compensaciones\Application\Services;

/**
 * Qué categorías puede leer un usuario: todas (Gestor), todas las activas (líder), las activas de su unidad
 * (colaborador) o ninguna (sin ficha, sin unidad o con la unidad inactiva).
 */
final class AlcanceDeLectura
{
    private function __construct(
        public readonly string $tipo,
        public readonly ?int $unidadId = null,
    ) {}

    public static function gestor(): self
    {
        return new self('gestor');
    }

    public static function lider(): self
    {
        return new self('lider');
    }

    public static function deUnidad(int $unidadId): self
    {
        return new self('unidad', $unidadId);
    }

    public static function ninguno(): self
    {
        return new self('ninguno');
    }

    public function esGestor(): bool
    {
        return $this->tipo === 'gestor';
    }

    public function esLider(): bool
    {
        return $this->tipo === 'lider';
    }

    public function esNinguno(): bool
    {
        return $this->tipo === 'ninguno';
    }

    /** Gestor y líder ven las unidades de cada categoría; el colaborador no. */
    public function veUnidades(): bool
    {
        return $this->esGestor() || $this->esLider();
    }
}
