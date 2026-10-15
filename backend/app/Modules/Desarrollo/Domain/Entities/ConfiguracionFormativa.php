<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\RecursoFormativo;

/**
 * Configuración formativa de una competencia: área, cursos y micro-contenidos vinculados.
 * Una competencia nunca configurada tiene versión 0, sin área y sin recursos.
 */
final class ConfiguracionFormativa
{
    /**
     * @param  array<int, RecursoFormativo>  $cursos  Incluye los archivados.
     * @param  array<int, RecursoFormativo>  $microcontenidos  Incluye los archivados.
     */
    public function __construct(
        public readonly int $competenciaId,
        public readonly ?int $areaFormativaId,
        public readonly ?string $areaNombre,
        public readonly int $version,
        public readonly ?int $actualizadoPor,
        public readonly ?string $actualizadoEn,
        public readonly array $cursos,
        public readonly array $microcontenidos,
    ) {}

    public static function vacia(int $competenciaId): self
    {
        return new self($competenciaId, null, null, 0, null, null, [], []);
    }

    /** @return array<int, RecursoFormativo> */
    public function cursosDisponibles(): array
    {
        return array_values(array_filter($this->cursos, fn (RecursoFormativo $r) => $r->estado->estaDisponible()));
    }

    /** @return array<int, RecursoFormativo> */
    public function microcontenidosDisponibles(): array
    {
        return array_values(array_filter($this->microcontenidos, fn (RecursoFormativo $r) => $r->estado->estaDisponible()));
    }

    /** Recursos disponibles (cursos + micro-contenidos): base de la cobertura. */
    public function recursosDisponibles(): int
    {
        return count($this->cursosDisponibles()) + count($this->microcontenidosDisponibles());
    }
}
