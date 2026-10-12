<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;

final class ObtenerCompetencia
{
    public function __construct(private readonly CompetenciaRepositoryInterface $competencias) {}

    /** @throws CompetenciaNoEncontrada */
    public function ejecutar(int $id): CompetenciaDTO
    {
        $competencia = $this->competencias->obtener($id) ?? throw new CompetenciaNoEncontrada;

        return CompetenciaDTO::desde($competencia);
    }
}
