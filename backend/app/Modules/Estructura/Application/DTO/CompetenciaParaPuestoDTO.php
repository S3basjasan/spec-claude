<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\ValueObjects\CompetenciaConGrados;

/**
 * Competencia activa que puede agregarse a un puesto, con los grados que define.
 */
final class CompetenciaParaPuestoDTO
{
    /**
     * @param  array<int, array{grado: string, descripcion: string}>  $grados
     */
    public function __construct(
        public readonly int $competenciaId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $tipo,
        public readonly array $grados,
    ) {}

    public static function desde(CompetenciaConGrados $competencia): self
    {
        $grados = [];
        foreach ($competencia->grados as $letra => $descripcion) {
            $grados[] = ['grado' => (string) $letra, 'descripcion' => $descripcion];
        }

        return new self(
            $competencia->competencia->id,
            $competencia->competencia->codigo,
            $competencia->competencia->nombre,
            $competencia->competencia->tipo->value,
            $grados,
        );
    }
}
