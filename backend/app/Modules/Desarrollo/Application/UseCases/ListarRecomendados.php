<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\AreaDTO;
use App\Modules\Desarrollo\Application\DTO\RecomendadoDTO;
use App\Modules\Desarrollo\Application\Ports\BrechasActivasInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\NivelesDeColaboradorInterface;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use Throwable;

/**
 * Competencias donde el colaborador tiene una brecha activa y que cuentan con al menos un recurso disponible.
 * Sin brechas, sin DNF o con un fallo del puerto, devuelve una lista vacía.
 */
final class ListarRecomendados
{
    public function __construct(
        private readonly CompetenciasInterface $competencias,
        private readonly CatalogoFormativoRepositoryInterface $repositorio,
        private readonly BrechasActivasInterface $brechas,
        private readonly NivelesDeColaboradorInterface $niveles,
    ) {}

    /**
     * @return array<int, RecomendadoDTO>
     */
    public function ejecutar(int $usuarioId): array
    {
        try {
            $brechas = $this->brechas->brechasDe($usuarioId);
        } catch (Throwable) {
            return [];
        }
        if ($brechas === []) {
            return [];
        }

        try {
            $niveles = $this->niveles->nivelesDe($usuarioId);
        } catch (Throwable) {
            $niveles = [];
        }

        $ids = array_map(fn (array $b) => $b['competencia_id'], $brechas);
        $competencias = [];
        foreach ($this->competencias->porIds($ids) as $c) {
            $competencias[$c['id']] = $c;
        }
        $configuraciones = $this->repositorio->configuracionesDe(array_keys($competencias));

        $resultado = [];
        foreach ($brechas as $brecha) {
            $c = $competencias[$brecha['competencia_id']] ?? null;
            if ($c === null || $c['estado'] !== 'Activo') {
                continue;
            }
            $config = $configuraciones[$c['id']];
            if ($config->recursosDisponibles() < 1) {
                continue;
            }

            $resultado[] = new RecomendadoDTO(
                $c['id'],
                $c['nombre'],
                $c['tipo'],
                $config->areaFormativaId === null ? null : new AreaDTO($config->areaFormativaId, (string) $config->areaNombre),
                $niveles[$c['id']] ?? null,
                $brecha['nivel_requerido'],
                count($config->cursosDisponibles()),
                count($config->microcontenidosDisponibles()),
            );
        }

        return $resultado;
    }
}
