<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use App\Modules\Estructura\Application\Services\AuditoriaDeCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;

final class ActualizarComportamientos
{
    public function __construct(
        private readonly CompetenciaRepositoryInterface $competencias,
        private readonly AuditoriaDeCompetencia $auditoria,
    ) {}

    /**
     * Actualiza los 16 comportamientos (4 por grado); también completa una competencia incompleta.
     * Permitido aunque la competencia esté inactiva.
     *
     * @param  array<int, array<int, string>>  $comportamientos  4 listas de 4 textos.
     *
     * @throws CompetenciaNoEncontrada
     * @throws ConflictoDeVersion
     */
    public function ejecutar(int $id, int $versionEsperada, array $comportamientos, ?int $actorId = null): CompetenciaDTO
    {
        $competencia = $this->competencias->actualizarComportamientos(
            $id,
            $versionEsperada,
            array_map(fn (array $textos) => array_map('trim', array_values($textos)), array_values($comportamientos)),
            $actorId,
        );

        return CompetenciaDTO::desde($competencia, $this->auditoria->para($competencia));
    }
}
