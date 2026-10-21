<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use App\Modules\Estructura\Application\Services\AuditoriaDeCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;

final class ActualizarPreguntas
{
    public function __construct(
        private readonly CompetenciaRepositoryInterface $competencias,
        private readonly AuditoriaDeCompetencia $auditoria,
    ) {}

    /**
     * Actualiza las cuatro preguntas; también completa una competencia incompleta.
     * Permitido aunque la competencia esté inactiva.
     *
     * @param  array<int, string>  $preguntas  4 textos.
     *
     * @throws CompetenciaNoEncontrada
     * @throws ConflictoDeVersion
     */
    public function ejecutar(int $id, int $versionEsperada, array $preguntas, ?int $actorId = null): CompetenciaDTO
    {
        $competencia = $this->competencias->actualizarPreguntas($id, $versionEsperada, array_map('trim', array_values($preguntas)), $actorId);

        return CompetenciaDTO::desde($competencia, $this->auditoria->para($competencia));
    }
}
