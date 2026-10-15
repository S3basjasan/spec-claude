<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\RecursoFormativo;

/**
 * Servicio inter-módulos del módulo Desarrollo (detrás de DesarrolloFacade). Solo lectura: ningún módulo
 * consumidor puede modificar asociaciones. Ver specs/005-catalogo-formativo/contracts/desarrollo-puertos.md.
 */
class DesarrolloService
{
    public function __construct(private readonly CatalogoFormativoRepositoryInterface $repositorio) {}

    /**
     * Oferta formativa disponible de una competencia (cursos y micro-contenidos no archivados).
     *
     * @return array{competencia_id: int, area_formativa: array{id: int, nombre: string}|null, cursos: array<int, array<string, mixed>>, microcontenidos: array<int, array<string, mixed>>}
     */
    public function getOfertaPorCompetencia(int $competenciaId): array
    {
        $config = $this->repositorio->configuracionDe($competenciaId);
        $aArreglo = fn (RecursoFormativo $r) => [
            'id' => $r->id,
            'nombre' => $r->nombre,
            'tipo' => $r->tipo,
            'duracion_minutos' => $r->duracionMinutos,
            'formador' => $r->formador,
            'url_destino' => $r->urlDestino,
        ];

        return [
            'competencia_id' => $competenciaId,
            'area_formativa' => $config->areaFormativaId === null ? null : ['id' => $config->areaFormativaId, 'nombre' => (string) $config->areaNombre],
            'cursos' => array_map($aArreglo, $config->cursosDisponibles()),
            'microcontenidos' => array_map($aArreglo, $config->microcontenidosDisponibles()),
        ];
    }

    public function competenciaTieneOferta(int $competenciaId): bool
    {
        return $this->repositorio->configuracionDe($competenciaId)->recursosDisponibles() >= 1;
    }
}
