<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use App\Modules\Estructura\Application\Services\AuditoriaDeCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;

final class ObtenerCompetencia
{
    public function __construct(
        private readonly CompetenciaRepositoryInterface $competencias,
        private readonly AuditoriaDeCompetencia $auditoria,
    ) {}

    /**
     * Devuelve la competencia con todo su contenido, aunque esté inactiva (consulta desde históricos).
     * La auditoría solo se incluye para el administrador.
     *
     * @throws CompetenciaNoEncontrada
     */
    public function ejecutar(int $id, bool $esAdministrador = false): CompetenciaDTO
    {
        $competencia = $this->competencias->obtener($id) ?? throw new CompetenciaNoEncontrada;

        return CompetenciaDTO::desde($competencia, $esAdministrador ? $this->auditoria->para($competencia) : null);
    }
}
