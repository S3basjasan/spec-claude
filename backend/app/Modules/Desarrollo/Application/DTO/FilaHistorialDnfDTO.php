<?php

namespace App\Modules\Desarrollo\Application\DTO;

/** Fila del historial de DNF: sin detalle de brechas. */
final class FilaHistorialDnfDTO
{
    /**
     * @param  array{id: int, nombre: string}|null  $colaborador
     * @param  array{id: int, nombre: string}|null  $departamento
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $fecha,
        public readonly string $alcance,
        public readonly ?array $colaborador,
        public readonly ?array $departamento,
        public readonly int $cantidadBrechas,
        public readonly ?string $prioridadMasAlta,
        public readonly bool $tienePlan,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'fecha' => $this->fecha,
            'alcance' => $this->alcance,
            'colaborador' => $this->colaborador,
            'departamento' => $this->departamento,
            'cantidad_brechas' => $this->cantidadBrechas,
            'prioridad_mas_alta' => $this->prioridadMasAlta,
            'tiene_plan' => $this->tienePlan,
        ];
    }
}
