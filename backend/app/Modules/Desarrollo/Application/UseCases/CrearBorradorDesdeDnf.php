<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Entities\AccionFormativa;
use App\Modules\Desarrollo\Domain\Entities\Plan;
use App\Modules\Desarrollo\Domain\Exceptions\DnfNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\DnfSinBrechas;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\NormalizadorDeNombre;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use Throwable;

/**
 * Crea el borrador de un plan a partir de las brechas de una DNF (FR-029): un plan en Borrador del año actual, de
 * nombre «Plan DNF {código de la DNF}», con una acción por competencia con brecha (esa competencia como objetivo) y los
 * colaboradores con brecha como participantes. Los demás datos de cada acción quedan por completar.
 */
final class CrearBorradorDesdeDnf
{
    public function __construct(
        private readonly PlanRepositoryInterface $planes,
        private readonly DnfRepositoryInterface $dnf,
        private readonly NombresDeReferenciaInterface $referencias,
        private readonly RelojInterface $reloj,
        private readonly NormalizadorDeNombre $normalizador,
    ) {}

    /**
     * @param  list<array{colaborador_id: int, competencia_id: int}>  $brechas
     * @return int identificador del plan creado
     *
     * @throws DnfNoEncontrada
     * @throws DnfSinBrechas
     */
    public function ejecutar(int $tenantId, int $actorId, int $dnfId, array $brechas): int
    {
        if ($brechas === []) {
            throw new DnfSinBrechas;
        }
        $dnf = $this->dnf->obtener($tenantId, $dnfId) ?? throw new DnfNoEncontrada;

        /** @var array<int, list<int>> $porCompetencia */
        $porCompetencia = [];
        foreach ($brechas as $brecha) {
            $colaboradores = &$porCompetencia[(int) $brecha['competencia_id']];
            $colaboradores ??= [];
            if (! in_array((int) $brecha['colaborador_id'], $colaboradores, true)) {
                $colaboradores[] = (int) $brecha['colaborador_id'];
            }
            unset($colaboradores);
        }

        $nombres = $this->nombresDeCompetencias(array_keys($porCompetencia));
        $acciones = [];
        foreach ($porCompetencia as $competenciaId => $colaboradorIds) {
            $acciones[] = new AccionFormativa(
                null,
                mb_substr($nombres[$competenciaId]['nombre'] ?? "Competencia {$competenciaId}", 0, 120),
                null, null, null, null, null, null, null, null, false, null,
                [$competenciaId],
                $colaboradorIds,
            );
        }

        $anio = (int) $this->reloj->hoy($actorId)->format('Y');
        $nombre = $this->nombreLibre($tenantId, $anio, "Plan DNF {$dnf->codigo}");

        $plan = $this->planes->crear(new Plan(
            null, $tenantId, null, $nombre, $this->normalizador->normalizar($nombre), $anio, null,
            EstadoPlan::Borrador, null, $dnfId, null, $actorId, $actorId, null, null, $acciones,
        ));

        return (int) $plan->id;
    }

    /** El nombre es único por año: si ya existe (caso improbable, hay un plan por DNF) se añade un número. */
    private function nombreLibre(int $tenantId, int $anio, string $base): string
    {
        $nombre = $base;
        for ($n = 2; $this->planes->existeNombre($tenantId, $anio, $this->normalizador->normalizar($nombre)); $n++) {
            $nombre = "{$base} ({$n})";
        }

        return $nombre;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{codigo: string, nombre: string, activa: bool}>
     */
    private function nombresDeCompetencias(array $ids): array
    {
        try {
            return $this->referencias->competencias($ids);
        } catch (Throwable) {
            return [];
        }
    }
}
