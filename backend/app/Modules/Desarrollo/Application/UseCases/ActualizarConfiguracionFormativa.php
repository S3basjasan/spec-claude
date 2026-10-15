<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\DetalleFormativoDTO;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Domain\Exceptions\AreaNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\CompetenciaInactiva;
use App\Modules\Desarrollo\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\ConfiguracionModificada;
use App\Modules\Desarrollo\Domain\Exceptions\RecursoNoDisponible;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeConfiguracionFormativa;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosConfiguracion;
use App\Modules\Desarrollo\Domain\ValueObjects\RecursoFormativo;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoRecurso;

/** Reemplaza de forma atómica el área, los cursos y los micro-contenidos de una competencia. */
final class ActualizarConfiguracionFormativa
{
    public function __construct(
        private readonly CompetenciasInterface $competencias,
        private readonly CatalogoFormativoRepositoryInterface $repositorio,
        private readonly ReglasDeConfiguracionFormativa $reglas,
        private readonly ObtenerDetalleFormativo $detalle,
    ) {}

    /**
     * @throws CompetenciaNoEncontrada
     * @throws ConfiguracionModificada
     * @throws CompetenciaInactiva
     * @throws RecursoNoDisponible
     * @throws AreaNoEncontrada
     */
    public function ejecutar(int $competenciaId, int $versionEsperada, DatosConfiguracion $datos, int $actorId): DetalleFormativoDTO
    {
        if (! $this->competencias->existe($competenciaId)) {
            throw new CompetenciaNoEncontrada;
        }

        $actual = $this->repositorio->configuracionDe($competenciaId);
        $ids = fn (array $recursos) => array_map(fn (RecursoFormativo $r) => $r->id, $recursos);

        $this->reglas->validar(
            $this->competencias->estaActiva($competenciaId),
            $datos,
            $ids($actual->cursos),
            $ids($actual->microcontenidos),
            $this->repositorio->idsDisponibles(TipoRecurso::Curso),
            $this->repositorio->idsDisponibles(TipoRecurso::Microcontenido),
            array_column($this->repositorio->areas(), 'id'),
        );

        $this->repositorio->guardarConfiguracion($competenciaId, $versionEsperada, $datos, $actorId);

        return $this->detalle->ejecutar($competenciaId, true, $actorId);
    }
}
