<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Entities\AccionFormativa;
use App\Modules\Desarrollo\Domain\Entities\Plan;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeEstadoDeAccion;
use App\Modules\Desarrollo\Domain\Services\DetectorDeCruces;
use App\Modules\Desarrollo\Domain\Services\ReglasDePlan;
use DateTimeImmutable;
use Throwable;

/**
 * Arma las respuestas de plan con la forma del contrato: nombres de referencia resueltos por lote, estado calculado
 * de cada acción, totales, avisos de cruce y los botones que corresponden al usuario.
 */
class ArmadorDePlanes
{
    public function __construct(
        private readonly PlanRepositoryInterface $planes,
        private readonly NombresDeUsuarioInterface $usuarios,
        private readonly NombresDeReferenciaInterface $referencias,
        private readonly ColaboradoresVigentesInterface $vigentes,
        private readonly DnfRepositoryInterface $dnf,
        private readonly RelojInterface $reloj,
        private readonly ReglasDePlan $reglas,
        private readonly CalculadorDeEstadoDeAccion $calculador,
        private readonly DetectorDeCruces $cruces,
    ) {}

    /** @return array<string, mixed> */
    public function plan(Plan $plan, int $usuarioId): array
    {
        $hoy = $this->reloj->hoy($usuarioId);
        $colaboradorIds = array_values(array_unique(array_merge(...array_map(fn (AccionFormativa $a) => $a->colaboradorIds, $plan->acciones) ?: [[]])));
        $competenciaIds = array_values(array_unique(array_merge(...array_map(fn (AccionFormativa $a) => $a->competenciaIds, $plan->acciones) ?: [[]])));

        $nombresColaboradores = $this->seguro(fn () => $this->referencias->colaboradores($plan->tenantId, $colaboradorIds), []);
        $competencias = $this->seguro(fn () => $this->referencias->competencias($competenciaIds), []);
        $vigentes = array_flip($this->seguro(fn () => $this->vigentes->vigentesPorIds($plan->tenantId, $colaboradorIds, $hoy->format('Y-m-d')), []));
        $usuarios = $this->usuarios->nombresDe(array_filter([$plan->creadoPor, $plan->actualizadoPor, $plan->aprobadorId]));

        $origen = null;
        if ($plan->origenDnfId !== null) {
            $dnf = $this->seguro(fn () => $this->dnf->obtener($plan->tenantId, $plan->origenDnfId), null);
            $origen = ['id' => $plan->origenDnfId, 'codigo' => $dnf?->codigo];
        }

        $avisos = $this->cruces->detectar(
            $this->planes->inscripcionesParaCruces($plan->tenantId, $colaboradorIds),
            array_values(array_filter(array_map(fn (AccionFormativa $a) => $a->id, $plan->acciones))),
        );

        return [
            'id' => $plan->id,
            'codigo' => $plan->codigo(),
            'nombre' => $plan->nombre,
            'anio' => $plan->anio,
            'descripcion' => $plan->descripcion,
            'estado' => $plan->estado->value,
            'aprobador' => $this->usuario($plan->aprobadorId, $usuarios),
            'origen_dnf' => $origen,
            'fecha_aprobacion' => $plan->fechaAprobacion?->format(DATE_ATOM),
            'cantidad_acciones' => $plan->cantidadAcciones(),
            'costo_total' => $plan->costoTotal(),
            'horas_totales' => $plan->horasTotales(),
            'creado_por' => $this->usuario($plan->creadoPor, $usuarios),
            'creado_en' => $plan->creadoEn?->format(DATE_ATOM),
            'modificado_por' => $this->usuario($plan->actualizadoPor, $usuarios),
            'modificado_en' => $plan->actualizadoEn?->format(DATE_ATOM),
            'acciones' => array_map(fn (AccionFormativa $a) => $this->accion($plan, $a, $hoy, $competencias, $nombresColaboradores, $vigentes), $plan->acciones),
            'avisos_cruce' => array_map(fn (array $aviso) => $aviso + ['nombre' => $nombresColaboradores[$aviso['colaborador_id']] ?? 'No disponible'], $avisos),
            'puede' => $this->reglas->puede($plan, $usuarioId),
        ];
    }

    /**
     * @param  array{plan: Plan, cantidad_acciones: int, costo_total: float}  $fila
     * @return array<string, mixed>
     */
    public function fila(array $fila, int $usuarioId): array
    {
        /** @var Plan $plan */
        $plan = $fila['plan'];
        $usuarios = $this->usuarios->nombresDe(array_filter([$plan->aprobadorId]));

        return [
            'id' => $plan->id,
            'codigo' => $plan->codigo(),
            'nombre' => $plan->nombre,
            'anio' => $plan->anio,
            'estado' => $plan->estado->value,
            'cantidad_acciones' => $fila['cantidad_acciones'],
            'costo_total' => $fila['costo_total'],
            'aprobador' => $this->usuario($plan->aprobadorId, $usuarios),
            'puede' => $this->reglas->puede($plan, $usuarioId),
        ];
    }

    /**
     * @param  array<int, array{codigo: string, nombre: string, activa: bool}>  $competencias
     * @param  array<int, string>  $nombresColaboradores
     * @param  array<int, int>  $vigentes
     * @return array<string, mixed>
     */
    private function accion(Plan $plan, AccionFormativa $a, DateTimeImmutable $hoy, array $competencias, array $nombresColaboradores, array $vigentes): array
    {
        $estado = $this->calculador->calcular($a->cancelada, $a->fechaInicio, $a->fechaFin, $hoy);
        $pendientes = $this->reglas->camposPendientes($a);

        return [
            'id' => $a->id,
            'plan_id' => $plan->id,
            'curso_id' => $a->cursoId,
            'nombre' => $a->nombre,
            'tipo' => $a->tipo?->value,
            'fecha_inicio' => $a->fechaInicio?->format('Y-m-d'),
            'fecha_fin' => $a->fechaFin?->format('Y-m-d'),
            'duracion_horas' => $a->duracionHoras,
            'formador' => $a->formador,
            'capacidad_maxima' => $a->capacidadMaxima,
            'costo' => $a->costo,
            'cancelada' => $a->cancelada,
            'motivo_cancelacion' => $a->motivoCancelacion,
            'estado_accion' => $estado->value,
            'competencias' => array_map(fn (int $id) => [
                'id' => $id,
                'codigo' => $competencias[$id]['codigo'] ?? null,
                'nombre' => $competencias[$id]['nombre'] ?? 'No disponible',
                'activa' => $competencias[$id]['activa'] ?? false,
            ], $a->competenciaIds),
            'participantes' => array_map(fn (int $id) => [
                'colaborador_id' => $id,
                'nombre' => $nombresColaboradores[$id] ?? 'No disponible',
                'vinculo_vigente' => isset($vigentes[$id]),
            ], $a->colaboradorIds),
            'completa' => $pendientes === [],
            'faltantes' => $pendientes,
        ];
    }

    /**
     * @param  array<int, string>  $usuarios
     * @return array{id: int, nombre: string}|null
     */
    private function usuario(?int $id, array $usuarios): ?array
    {
        return $id === null ? null : ['id' => $id, 'nombre' => $usuarios[$id] ?? 'No disponible'];
    }

    /**
     * Las lecturas a otros módulos no deben tumbar la respuesta: si fallan se muestra «No disponible».
     *
     * @template T
     *
     * @param  callable(): T  $lectura
     * @param  T  $porDefecto
     * @return T
     */
    private function seguro(callable $lectura, mixed $porDefecto): mixed
    {
        try {
            return $lectura();
        } catch (Throwable) {
            return $porDefecto;
        }
    }
}
