<?php

namespace App\Modules\Desarrollo\Application\DTO;

/** Resultado de un colaborador en una DNF; `brechas` es nulo en el resumen de una masiva. */
final class ResultadoColaboradorDTO
{
    /**
     * @param  array{id: int, codigo: string, nombre: string}|null  $puesto
     * @param  list<BrechaDTO>|null  $brechas
     */
    public function __construct(
        public readonly int $colaboradorId,
        public readonly string $nombre,
        public readonly ?array $puesto,
        public readonly int $cantidadBrechas,
        public readonly ?string $prioridadMasAlta,
        public readonly int $sinEvaluacion,
        public readonly ?array $brechas,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $datos = [
            'colaborador_id' => $this->colaboradorId,
            'nombre' => $this->nombre,
            'puesto' => $this->puesto,
            'cantidad_brechas' => $this->cantidadBrechas,
            'prioridad_mas_alta' => $this->prioridadMasAlta,
            'sin_evaluacion' => $this->sinEvaluacion,
        ];
        if ($this->brechas !== null) {
            $datos['brechas'] = array_map(fn (BrechaDTO $b) => $b->toArray(), $this->brechas);
        }

        return $datos;
    }
}
