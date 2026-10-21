<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use App\Modules\Estructura\Application\Services\AuditoriaDeCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\EscalaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\EscalaSinCambio;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;

final class AplicarEscala
{
    public function __construct(
        private readonly CompetenciaRepositoryInterface $competencias,
        private readonly AuditoriaDeCompetencia $auditoria,
    ) {}

    /**
     * Cambia solo los nombres de los grados: las descripciones, los comportamientos y las asignaciones de
     * otros módulos no se modifican. Permitido aunque la competencia esté inactiva.
     *
     * @throws CompetenciaNoEncontrada
     * @throws EscalaNoEncontrada
     * @throws EscalaSinCambio
     */
    public function ejecutar(int $id, int $escalaId, ?int $actorId = null): CompetenciaDTO
    {
        $competencia = $this->competencias->aplicarEscala($id, $escalaId, $actorId);

        return CompetenciaDTO::desde($competencia, $this->auditoria->para($competencia));
    }
}
