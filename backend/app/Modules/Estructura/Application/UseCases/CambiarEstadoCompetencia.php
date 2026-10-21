<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use App\Modules\Estructura\Application\Services\AuditoriaDeCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaIncompleta;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;

final class CambiarEstadoCompetencia
{
    public function __construct(
        private readonly CompetenciaRepositoryInterface $competencias,
        private readonly AuditoriaDeCompetencia $auditoria,
    ) {}

    /**
     * Activa o desactiva la competencia. Una competencia incompleta no se puede activar.
     *
     * @param  string  $estado  `Activo` o `Inactivo`.
     *
     * @throws CompetenciaNoEncontrada
     * @throws EstadoSinCambio
     * @throws CompetenciaIncompleta
     */
    public function ejecutar(int $id, string $estado, ?int $actorId = null): CompetenciaDTO
    {
        $nuevo = EstadoCompetencia::from($estado);
        $actual = $this->competencias->obtener($id) ?? throw new CompetenciaNoEncontrada;

        if ($actual->estado === $nuevo) {
            throw new EstadoSinCambio;
        }

        if ($nuevo === EstadoCompetencia::Activo && ! $actual->estaCompleta()) {
            throw new CompetenciaIncompleta($actual->faltantes());
        }

        $competencia = $this->competencias->cambiarEstado($id, $nuevo, $actorId);

        return CompetenciaDTO::desde($competencia, $this->auditoria->para($competencia));
    }
}
