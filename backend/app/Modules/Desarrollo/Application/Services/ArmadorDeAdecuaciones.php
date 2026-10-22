<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\DTO\DiagnosticoDTO;
use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Domain\Entities\Adecuacion;
use App\Modules\Desarrollo\Domain\Entities\ParametrosDeAdecuacion;
use App\Modules\Desarrollo\Domain\Entities\ResultadoDeAdecuacion;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeCumplimiento;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePreparacion;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePuntaje;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Da forma de respuesta a los diagnósticos y a las adecuaciones guardadas, resolviendo nombres de colaboradores,
 * puestos y departamentos por lote (sin N+1) mediante los puertos del módulo.
 */
final class ArmadorDeAdecuaciones
{
    public const AVISO_GUIA = 'Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.';

    public function __construct(
        private readonly NombresDeReferenciaInterface $nombres,
        private readonly DepartamentoDeColaboradorInterface $departamentos,
        private readonly DepartamentosInterface $unidades,
        private readonly CalculadorDeCumplimiento $cumplimiento,
        private readonly CalculadorDePuntaje $puntaje,
        private readonly CalculadorDePreparacion $preparacion,
    ) {}

    /**
     * Contexto de nombres para un conjunto de colaboradores y puestos.
     *
     * @param  list<int>  $colaboradorIds
     * @param  list<int>  $puestoIds
     * @param  list<string>  $fuentes
     * @return array{colaboradores: array<int, string>, puestos: array<int, string>, departamentos: array<int, string|null>}
     */
    public function contexto(int $tenantId, array $colaboradorIds, array $puestoIds, array &$fuentes): array
    {
        $colaboradorIds = array_values(array_unique($colaboradorIds));
        $puestoIds = array_values(array_unique(array_filter($puestoIds)));
        $contexto = ['colaboradores' => [], 'puestos' => [], 'departamentos' => []];

        try {
            $contexto['colaboradores'] = $colaboradorIds === [] ? [] : $this->nombres->colaboradores($tenantId, $colaboradorIds);
        } catch (Throwable) {
            $fuentes[] = 'personal';
        }
        try {
            foreach ($puestoIds === [] ? [] : $this->nombres->puestos($puestoIds) as $id => $p) {
                $contexto['puestos'][(int) $id] = (string) $p['nombre'];
            }
        } catch (Throwable) {
            $fuentes[] = 'estructura';
        }
        try {
            $unidadPorColaborador = $colaboradorIds === [] ? [] : $this->departamentos->actuales($tenantId, $colaboradorIds, CarbonImmutable::today()->toDateString());
            $nombresDeUnidades = $this->unidades->porIds(array_values(array_unique(array_filter($unidadPorColaborador))));
            foreach ($colaboradorIds as $id) {
                $unidad = $unidadPorColaborador[$id] ?? null;
                $contexto['departamentos'][$id] = $unidad === null ? null : ($nombresDeUnidades[$unidad] ?? null);
            }
        } catch (Throwable) {
            $fuentes[] = 'personal';
        }

        return $contexto;
    }

    /**
     * Respuesta de la previsualización.
     *
     * @return array<string, mixed>
     */
    public function diagnostico(DiagnosticoDTO $d, int $tenantId, ParametrosDeAdecuacion $parametros): array
    {
        $fuentes = $d->fuentesNoDisponibles;
        $ctx = $this->contexto($tenantId, [$d->colaboradorId], [$d->puestoActualId ?? 0, $d->puestoObjetivoId], $fuentes);

        return [
            'colaborador' => ['id' => $d->colaboradorId, 'nombre' => $ctx['colaboradores'][$d->colaboradorId] ?? 'No disponible', 'departamento' => $ctx['departamentos'][$d->colaboradorId] ?? null],
            'puesto_actual' => $d->puestoActualId === null ? null : ['id' => $d->puestoActualId, 'nombre' => $ctx['puestos'][$d->puestoActualId] ?? 'No disponible'],
            'puesto_objetivo' => ['id' => $d->puestoObjetivoId, 'nombre' => $d->puestoObjetivoNombre],
            'inscripcion_id' => $d->inscripcionId,
            'tipo_cambio' => $d->tipoCambio,
            'experiencia_requerida_anios' => $d->experienciaRequeridaAnios,
            'experiencia_de_la_persona_anios' => $d->experienciaDeLaPersona,
            'resultados' => array_map($this->resultado(...), $d->resultados),
            'cumplimiento_por_dimension' => $this->redondear($this->cumplimiento->porDimension($d->resultados)),
            'cumplimiento_por_dimension_actual' => $this->redondear($this->cumplimiento->porDimensionFrenteAlActual($d->resultados)),
            'cumplimiento_actual' => $this->puntaje->calcular($this->cumplimiento->porDimensionFrenteAlActual($d->resultados), $parametros->pesos),
            'cumplimiento_objetivo' => $this->puntaje->calcular($this->cumplimiento->porDimension($d->resultados), $parametros->pesos),
            'preparacion_provisoria' => $this->preparacion->calcular($d->resultados, $parametros->umbralMeses)->value,
            'umbral_meses' => $parametros->umbralMeses,
            'avisos' => $d->avisos,
            'fuentes_no_disponibles' => array_values(array_unique($fuentes)),
        ];
    }

    /**
     * Un resultado en la forma del contrato.
     *
     * @return array<string, mixed>
     */
    public function resultado(ResultadoDeAdecuacion $r, bool $conCumplimiento = true): array
    {
        $fila = [
            'dimension' => $r->dimension->value,
            'tipo_elemento' => $r->tipoElemento,
            'elemento_id' => $r->elementoId,
            'nombre' => $r->nombreElemento,
            'requerido_actual' => $r->requeridoActualNivel?->value,
            'requerido_objetivo' => $r->requeridoObjetivoNivel?->value,
            'valor' => $r->valorNivel?->value,
            'requerido_actual_anios' => $r->requeridoActualAnios,
            'requerido_objetivo_anios' => $r->requeridoObjetivoAnios,
            'valor_anios' => $r->valorAnios,
            'diferencia' => $r->diferencia,
            'estado' => $r->estado->value,
            'estado_etiqueta' => $r->estado->etiqueta(),
            'tiempo_cierre_meses' => $r->tiempoCierreMeses,
        ];

        if ($conCumplimiento) {
            $c = $r->cumplimientoObjetivo();
            $fila['cumplimiento'] = $c === null ? null : round($c, 4);
        }

        return $fila;
    }

    /**
     * @param  array<string, float|null>  $valores
     * @return array<string, float|null>
     */
    public function redondear(array $valores): array
    {
        return array_map(fn (?float $v) => $v === null ? null : round($v, 4), $valores);
    }

    /**
     * Detalle de una adecuación guardada.
     *
     * @return array<string, mixed>
     */
    public function adecuacion(Adecuacion $a, bool $esVigente, ?string $generadaPor, ?array $ruta, int $tenantId): array
    {
        $fuentes = [];
        $ctx = $this->contexto($tenantId, [$a->colaboradorId], [$a->puestoActualId ?? 0, $a->puestoObjetivoId], $fuentes);
        $brechas = $a->cantidadDeBrechas();
        $motivo = $brechas === 0 ? 'No hay brechas.' : 'Disponible cuando exista el Plan de Desarrollo Individual.';

        return [
            'id' => $a->id,
            'codigo' => $a->codigo?->valor(),
            'fecha' => $a->creadoEn?->format('Y-m-d\TH:i:sP'),
            'generada_por' => $generadaPor,
            'colaborador' => ['id' => $a->colaboradorId, 'nombre' => $ctx['colaboradores'][$a->colaboradorId] ?? 'No disponible', 'departamento' => $ctx['departamentos'][$a->colaboradorId] ?? null],
            'puesto_actual' => $a->puestoActualId === null ? null : ['id' => $a->puestoActualId, 'nombre' => $ctx['puestos'][$a->puestoActualId] ?? 'No disponible'],
            'puesto_objetivo' => ['id' => $a->puestoObjetivoId, 'nombre' => $ctx['puestos'][$a->puestoObjetivoId] ?? 'No disponible'],
            'inscripcion' => $a->inscripcionId === null || $ruta === null ? null : ['id' => $a->inscripcionId, 'ruta_nombre' => $ruta['nombre']],
            'experiencia_requerida_anios' => $a->experienciaRequeridaAnios,
            'preparacion' => $a->preparacion->value,
            'preparacion_etiqueta' => $a->preparacion->etiqueta(),
            'umbral_meses' => $a->umbralMeses,
            'observaciones' => $a->observaciones,
            'es_vigente' => $esVigente,
            'resultados' => array_map($this->resultado(...), $a->resultados),
            'cumplimiento_por_dimension' => $this->redondear($this->cumplimiento->porDimension($a->resultados)),
            'cumplimiento_por_dimension_actual' => $this->redondear($this->cumplimiento->porDimensionFrenteAlActual($a->resultados)),
            'brechas' => $brechas,
            'tiempo_maximo_cierre_meses' => $a->tiempoMaximoDeCierre(),
            'acciones' => [
                'crear_plan' => ['habilitado' => false, 'motivo' => $motivo],
                'incluir_programa' => ['habilitado' => false, 'motivo' => $motivo],
            ],
            'fuentes_no_disponibles' => array_values(array_unique($fuentes)),
        ];
    }
}
