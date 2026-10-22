<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeAdecuaciones;
use App\Modules\Desarrollo\Domain\Entities\Adecuacion;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeCumplimiento;
use App\Modules\Desarrollo\Domain\ValueObjects\Preparacion;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Mapa de talento (FR-028): la adecuación vigente de cada colaborador por puesto objetivo, con brechas y tiempo máximo de
 * cierre, filtrable por departamento, ruta y preparación. Los indicadores cuentan por preparación sobre el conjunto
 * filtrado por departamento y ruta (sin el filtro de preparación, para mostrar la distribución).
 */
final class ObtenerMapaDeTalento
{
    public function __construct(
        private readonly AdecuacionesRepositoryInterface $adecuaciones,
        private readonly DepartamentoDeColaboradorInterface $departamentoDe,
        private readonly DepartamentosInterface $departamentos,
        private readonly ArmadorDeAdecuaciones $armador,
        private readonly CalculadorDeCumplimiento $cumplimiento,
    ) {}

    /**
     * @param  array{departamento_id: int|null, ruta_id: int|null, preparacion: string|null}  $filtros
     * @return array<string, mixed>
     */
    public function ejecutar(int $tenantId, array $filtros, int $pagina, int $porPagina): array
    {
        $fuentes = [];
        $vigentes = $this->adecuaciones->vigentes($tenantId, array_filter(['ruta_id' => $filtros['ruta_id']], fn ($v) => $v !== null));

        $colaboradorIds = array_values(array_unique(array_map(fn (Adecuacion $a) => $a->colaboradorId, $vigentes)));
        $unidades = $this->unidadesDe($tenantId, $colaboradorIds, $fuentes);

        if ($filtros['departamento_id'] !== null) {
            $vigentes = array_values(array_filter($vigentes, fn (Adecuacion $a) => ($unidades[$a->colaboradorId] ?? null) === $filtros['departamento_id']));
        }

        $kpis = array_fill_keys(array_map(fn (Preparacion $p) => $p->value, Preparacion::cases()), 0);
        foreach ($vigentes as $a) {
            $kpis[$a->preparacion->value]++;
        }

        if ($filtros['preparacion'] !== null) {
            $vigentes = array_values(array_filter($vigentes, fn (Adecuacion $a) => $a->preparacion->value === $filtros['preparacion']));
        }

        $total = count($vigentes);
        $pagina = max(1, $pagina);
        $visibles = array_slice($vigentes, ($pagina - 1) * $porPagina, $porPagina);

        $contexto = $this->armador->contexto(
            $tenantId,
            array_map(fn (Adecuacion $a) => $a->colaboradorId, $visibles),
            array_merge(...array_map(fn (Adecuacion $a) => [$a->puestoActualId ?? 0, $a->puestoObjetivoId], $visibles)) ?: [],
            $fuentes,
        );
        $rutas = $this->rutasDe($visibles, $fuentes);

        return [
            'data' => array_map(fn (Adecuacion $a) => [
                'adecuacion_id' => $a->id,
                'codigo' => $a->codigo?->valor(),
                'colaborador' => [
                    'id' => $a->colaboradorId,
                    'nombre' => $contexto['colaboradores'][$a->colaboradorId] ?? 'No disponible',
                    'departamento' => $contexto['departamentos'][$a->colaboradorId] ?? null,
                ],
                'ruta' => $a->inscripcionId === null ? null : ($rutas[$a->inscripcionId] ?? null),
                'puesto_actual' => $a->puestoActualId === null ? null : ['id' => $a->puestoActualId, 'nombre' => $contexto['puestos'][$a->puestoActualId] ?? 'No disponible'],
                'puesto_objetivo' => ['id' => $a->puestoObjetivoId, 'nombre' => $contexto['puestos'][$a->puestoObjetivoId] ?? 'No disponible'],
                'preparacion' => $a->preparacion->value,
                'preparacion_etiqueta' => $a->preparacion->etiqueta(),
                'cumplimiento_por_dimension' => $this->armador->redondear($this->cumplimiento->porDimension($a->resultados)),
                'brechas' => $a->cantidadDeBrechas(),
                'tiempo_maximo_cierre_meses' => $a->tiempoMaximoDeCierre(),
            ], $visibles),
            'kpis' => $kpis,
            'meta' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $porPagina))],
            'fuentes_no_disponibles' => array_values(array_unique($fuentes)),
        ];
    }

    /**
     * @param  list<int>  $colaboradorIds
     * @param  list<string>  $fuentes
     * @return array<int, int|null> colaborador_id => unidad_id
     */
    private function unidadesDe(int $tenantId, array $colaboradorIds, array &$fuentes): array
    {
        if ($colaboradorIds === []) {
            return [];
        }

        try {
            return $this->departamentoDe->actuales($tenantId, $colaboradorIds, CarbonImmutable::today()->toDateString());
        } catch (Throwable) {
            $fuentes[] = 'personal';

            return [];
        }
    }

    /**
     * @param  list<Adecuacion>  $adecuaciones
     * @param  list<string>  $fuentes
     * @return array<int, array{id: int, nombre: string}>
     */
    private function rutasDe(array $adecuaciones, array &$fuentes): array
    {
        $inscripciones = array_values(array_unique(array_filter(array_map(fn (Adecuacion $a) => $a->inscripcionId, $adecuaciones))));
        if ($inscripciones === []) {
            return [];
        }

        try {
            return $this->adecuaciones->rutasDeInscripciones($inscripciones);
        } catch (Throwable) {
            $fuentes[] = 'rutas';

            return [];
        }
    }
}
