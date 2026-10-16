<?php

namespace App\Modules\Desarrollo\Application\DTO;

/** DNF lista para presentar, con los nombres de referencia ya resueltos. */
final class DnfDTO
{
    /**
     * @param  array{id: int, nombre: string}|null  $departamento
     * @param  array{id: int, nombre: string}|null  $generadoPor
     * @param  array{id: int, generado_por: array{id: int, nombre: string}|null, generado_en: string|null}|null  $planFormacion
     * @param  list<ResultadoColaboradorDTO>  $colaboradores
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $alcance,
        public readonly string $fecha,
        public readonly ?array $departamento,
        public readonly ?array $generadoPor,
        public readonly int $competenciasOmitidas,
        public readonly ?array $planFormacion,
        public readonly int $totalColaboradores,
        public readonly int $cantidadBrechas,
        public readonly ?string $prioridadMasAlta,
        public readonly int $sinEvaluacion,
        public readonly array $colaboradores,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'alcance' => $this->alcance,
            'fecha' => $this->fecha,
            'departamento' => $this->departamento,
            'generado_por' => $this->generadoPor,
            'competencias_omitidas' => $this->competenciasOmitidas,
            'plan_formacion' => $this->planFormacion,
            'resumen' => [
                'colaboradores' => $this->totalColaboradores,
                'cantidad_brechas' => $this->cantidadBrechas,
                'prioridad_mas_alta' => $this->prioridadMasAlta,
                'sin_evaluacion' => $this->sinEvaluacion,
            ],
            'colaboradores' => array_map(fn (ResultadoColaboradorDTO $c) => $c->toArray(), $this->colaboradores),
        ];
    }
}
