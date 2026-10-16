<?php

namespace App\Modules\Desarrollo\Application\DTO;

use App\Modules\Desarrollo\Domain\Entities\Brecha;

/** Brecha lista para presentar: incluye el nombre de la competencia resuelto en el momento de la lectura. */
final class BrechaDTO
{
    public function __construct(
        public readonly int $competenciaId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $tipo,
        public readonly string $nivelRequerido,
        public readonly ?string $nivelActual,
        public readonly ?int $diferencia,
        public readonly ?float $brechaPorcentaje,
        public readonly ?string $prioridad,
        public readonly string $estado,
        public readonly bool $competenciaActiva,
    ) {}

    /**
     * @param  array{codigo: string, nombre: string, activa: bool}|null  $competencia
     */
    public static function desde(Brecha $brecha, ?array $competencia, string $noDisponible = 'No disponible'): self
    {
        return new self(
            $brecha->competenciaId,
            $competencia['codigo'] ?? sprintf('COM-%03d', $brecha->competenciaId),
            $competencia['nombre'] ?? $noDisponible,
            $brecha->tipo->value,
            $brecha->nivelRequerido->value,
            $brecha->nivelActual?->value,
            $brecha->diferencia,
            $brecha->brechaPorcentaje,
            $brecha->prioridad?->value,
            $brecha->estado->value,
            $competencia['activa'] ?? false,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'competencia_id' => $this->competenciaId,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'nivel_requerido' => $this->nivelRequerido,
            'nivel_actual' => $this->nivelActual,
            'diferencia' => $this->diferencia,
            'brecha_porcentaje' => $this->brechaPorcentaje,
            'prioridad' => $this->prioridad,
            'estado' => $this->estado,
            'competencia_activa' => $this->competenciaActiva,
        ];
    }
}
