<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\NivelesActualesInterface;

/** Puerto falso de niveles actuales por colaborador; registra cuántas veces fue consultado. */
final class NivelesActualesFalsos implements NivelesActualesInterface
{
    public int $consultas = 0;

    /**
     * @param  array<int, array<int, string>>  $niveles  colaborador_id => [competencia_id => nivel]
     */
    public function __construct(public array $niveles = []) {}

    public function nivelesDe(int $tenantId, array $colaboradorIds): array
    {
        $this->consultas++;

        return array_intersect_key($this->niveles, array_flip($colaboradorIds));
    }
}
