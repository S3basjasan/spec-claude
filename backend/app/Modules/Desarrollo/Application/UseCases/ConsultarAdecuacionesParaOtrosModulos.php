<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Domain\Entities\Adecuacion;
use App\Modules\Desarrollo\Domain\Entities\ResultadoDeAdecuacion;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Consultas de solo lectura que ofrece la Facade al Plan de Desarrollo Individual y a Reportes. Devuelven únicamente
 * cabeceras y brechas de las adecuaciones vigentes: sin nombres, observaciones ni quién las generó.
 */
final class ConsultarAdecuacionesParaOtrosModulos
{
    public function __construct(
        private readonly AdecuacionesRepositoryInterface $adecuaciones,
        private readonly DepartamentoDeColaboradorInterface $departamentoDe,
    ) {}

    /**
     * La adecuación vigente del colaborador para ese puesto objetivo, con sus brechas, o `null` si no tiene.
     *
     * @return array{adecuacion_id: int, codigo: string, fecha: string|null, preparacion: string, umbral_meses: int, puesto_actual_id: int|null, puesto_objetivo_id: int, inscripcion_id: int|null, brechas: list<array{dimension: string, tipo_elemento: string|null, elemento_id: int|null, nombre: string|null, diferencia: float|null, tiempo_cierre_meses: int|null}>}|null
     */
    public function ultimaAdecuacion(int $tenantId, int $colaboradorId, int $puestoObjetivoId): ?array
    {
        $vigentes = $this->adecuaciones->vigentes($tenantId, ['puesto_objetivo_id' => $puestoObjetivoId, 'colaborador_ids' => [$colaboradorId]]);
        $a = $vigentes[0] ?? null;
        if ($a === null) {
            return null;
        }

        return [
            'adecuacion_id' => (int) $a->id,
            'codigo' => (string) $a->codigo?->valor(),
            'fecha' => $a->creadoEn?->format('Y-m-d\TH:i:sP'),
            'preparacion' => $a->preparacion->value,
            'umbral_meses' => $a->umbralMeses,
            'puesto_actual_id' => $a->puestoActualId,
            'puesto_objetivo_id' => $a->puestoObjetivoId,
            'inscripcion_id' => $a->inscripcionId,
            'brechas' => array_map(fn (ResultadoDeAdecuacion $r) => [
                'dimension' => $r->dimension->value,
                'tipo_elemento' => $r->tipoElemento,
                'elemento_id' => $r->elementoId,
                'nombre' => $r->nombreElemento,
                'diferencia' => $r->diferencia,
                'tiempo_cierre_meses' => $r->tiempoCierreMeses,
            ], $a->brechas()),
        ];
    }

    /**
     * Cabeceras de las adecuaciones vigentes. Filtros: fecha_desde, fecha_hasta, colaborador_id, puesto_objetivo_id,
     * preparacion y departamento_id.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<array{adecuacion_id: int, colaborador_id: int, puesto_objetivo_id: int, preparacion: string, brechas: int, tiempo_maximo_cierre_meses: int|null, fecha: string|null}>
     */
    public function adecuacionesPorFiltros(int $tenantId, array $filtros = []): array
    {
        $paraElRepositorio = [];
        foreach (['preparacion', 'puesto_objetivo_id'] as $clave) {
            if (! empty($filtros[$clave])) {
                $paraElRepositorio[$clave] = $filtros[$clave];
            }
        }
        if (! empty($filtros['colaborador_id'])) {
            $paraElRepositorio['colaborador_ids'] = [(int) $filtros['colaborador_id']];
        }

        $vigentes = $this->adecuaciones->vigentes($tenantId, $paraElRepositorio);
        $vigentes = array_values(array_filter($vigentes, fn (Adecuacion $a) => $this->cumpleFechas($a, $filtros)));

        if (! empty($filtros['departamento_id'])) {
            $ids = array_values(array_unique(array_map(fn (Adecuacion $a) => $a->colaboradorId, $vigentes)));
            $unidades = $ids === [] ? [] : $this->unidadesDe($tenantId, $ids);
            $vigentes = array_values(array_filter($vigentes, fn (Adecuacion $a) => ($unidades[$a->colaboradorId] ?? null) === (int) $filtros['departamento_id']));
        }

        return array_map(fn (Adecuacion $a) => [
            'adecuacion_id' => (int) $a->id,
            'colaborador_id' => $a->colaboradorId,
            'puesto_objetivo_id' => $a->puestoObjetivoId,
            'preparacion' => $a->preparacion->value,
            'brechas' => $a->cantidadDeBrechas(),
            'tiempo_maximo_cierre_meses' => $a->tiempoMaximoDeCierre(),
            'fecha' => $a->creadoEn?->format('Y-m-d\TH:i:sP'),
        ], $vigentes);
    }

    /** @param  array<string, mixed>  $filtros */
    private function cumpleFechas(Adecuacion $a, array $filtros): bool
    {
        $dia = $a->creadoEn?->format('Y-m-d');
        if ($dia === null) {
            return true;
        }

        return (empty($filtros['fecha_desde']) || $dia >= $filtros['fecha_desde']) && (empty($filtros['fecha_hasta']) || $dia <= $filtros['fecha_hasta']);
    }

    /**
     * @param  list<int>  $colaboradorIds
     * @return array<int, int|null>
     */
    private function unidadesDe(int $tenantId, array $colaboradorIds): array
    {
        try {
            return $this->departamentoDe->actuales($tenantId, $colaboradorIds, CarbonImmutable::today()->toDateString());
        } catch (Throwable) {
            return [];
        }
    }
}
