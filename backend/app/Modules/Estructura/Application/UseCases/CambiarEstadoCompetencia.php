<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;

final class CambiarEstadoCompetencia
{
    public function __construct(private readonly CompetenciaRepositoryInterface $competencias) {}

    /**
     * @param  string  $estado  `Activo` o `Inactivo`.
     *
     * @throws CompetenciaNoEncontrada
     * @throws EstadoSinCambio
     */
    public function ejecutar(int $id, string $estado, ?int $actorId = null): CompetenciaDTO
    {
        $competencia = $this->competencias->cambiarEstado($id, EstadoCompetencia::from($estado), $actorId);

        return CompetenciaDTO::desde($competencia);
    }
}
