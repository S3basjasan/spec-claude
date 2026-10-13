<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\PuestoDTO;
use App\Modules\Estructura\Domain\Exceptions\CompetenciasDePuestoInvalidas;
use App\Modules\Estructura\Domain\Exceptions\NombreDePuestoDuplicado;
use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;
use App\Modules\Estructura\Domain\Services\ReglasDeCompetenciasDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\AsignacionDeCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\DatosPuesto;

final class CrearPuesto
{
    public function __construct(
        private readonly PuestoRepositoryInterface $puestos,
        private readonly ReglasDeCompetenciasDePuesto $reglas,
    ) {}

    /**
     * Crea un puesto Activo con su código, sus listas y su historial.
     *
     * @throws CompetenciasDePuestoInvalidas
     * @throws NombreDePuestoDuplicado
     */
    public function ejecutar(DatosPuesto $datos, ?int $actorId = null): PuestoDTO
    {
        $ids = array_map(fn (AsignacionDeCompetencia $a) => $a->competenciaId, $datos->competencias);
        $this->reglas->validar($datos->competencias, $this->puestos->competenciasPorIds($ids));

        return PuestoDTO::desde($this->puestos->crear($datos, $actorId));
    }
}
