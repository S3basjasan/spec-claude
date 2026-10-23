<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Desarrollo\Domain\Entities\AccionFormativa;
use App\Modules\Desarrollo\Domain\Entities\Plan;
use App\Modules\Desarrollo\Domain\Exceptions\NombreDePlanDuplicado;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroAcciones;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroPlanes;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoHistorialPlan;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionCompetenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionParticipanteModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentPlanRepository implements PlanRepositoryInterface
{
    public function transaccion(callable $accion): mixed
    {
        return DB::transaction($accion);
    }

    public function crear(Plan $plan): Plan
    {
        for ($intento = 1; ; $intento++) {
            try {
                $id = DB::transaction(function () use ($plan): int {
                    $numero = (int) PlanModel::query()->where('tenant_id', $plan->tenantId)->max('numero') + 1;
                    $modelo = PlanModel::query()->create([
                        'tenant_id' => $plan->tenantId,
                        'numero' => $numero,
                        'codigo' => CodigoPlan::desde($numero),
                        'nombre' => $plan->nombre,
                        'nombre_normalizado' => $plan->nombreNormalizado,
                        'anio' => $plan->anio,
                        'descripcion' => $plan->descripcion,
                        'estado' => $plan->estado->value,
                        'aprobador_id' => $plan->aprobadorId,
                        'origen_dnf_id' => $plan->origenDnfId,
                        'created_by' => $plan->creadoPor,
                        'updated_by' => $plan->actualizadoPor,
                    ]);
                    foreach ($plan->acciones as $accion) {
                        $this->insertarAccion($modelo->id, $accion, $plan->actualizadoPor);
                    }

                    return $modelo->id;
                });

                return $this->obtener($plan->tenantId, $id) ?? $plan;
            } catch (UniqueConstraintViolationException $e) {
                if ($this->existeNombre($plan->tenantId, $plan->anio, $plan->nombreNormalizado)) {
                    throw new NombreDePlanDuplicado;
                }
                if ($intento >= 3) {
                    throw $e;
                }
            }
        }
    }

    public function reemplazar(Plan $plan): Plan
    {
        try {
            DB::transaction(function () use ($plan): void {
                $modelo = PlanModel::query()->where('tenant_id', $plan->tenantId)->findOrFail($plan->id);
                $modelo->update([
                    'nombre' => $plan->nombre,
                    'nombre_normalizado' => $plan->nombreNormalizado,
                    'anio' => $plan->anio,
                    'descripcion' => $plan->descripcion,
                    'aprobador_id' => $plan->aprobadorId,
                    'updated_by' => $plan->actualizadoPor,
                ]);

                $existentes = PlanAccionModel::query()->where('plan_id', $modelo->id)->pluck('id')->map(fn ($i) => (int) $i)->all();
                $conservadas = [];
                foreach ($plan->acciones as $accion) {
                    if ($accion->id !== null && in_array($accion->id, $existentes, true)) {
                        $this->actualizarAccion($accion, $plan->actualizadoPor);
                        $conservadas[] = $accion->id;
                    } else {
                        $conservadas[] = $this->insertarAccion($modelo->id, $accion, $plan->actualizadoPor);
                    }
                }
                foreach (array_diff($existentes, $conservadas) as $quitada) {
                    PlanAccionCompetenciaModel::query()->where('plan_accion_id', $quitada)->delete();
                    PlanAccionParticipanteModel::query()->where('plan_accion_id', $quitada)->delete();
                    PlanAccionModel::query()->whereKey($quitada)->delete();
                }
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($this->existeNombre($plan->tenantId, $plan->anio, $plan->nombreNormalizado, $plan->id)) {
                throw new NombreDePlanDuplicado;
            }
            throw $e;
        }

        return $this->obtener($plan->tenantId, (int) $plan->id) ?? $plan;
    }

    public function obtener(int $tenantId, int $id): ?Plan
    {
        $modelo = PlanModel::query()
            ->with(['acciones.competencias', 'acciones.participantes'])
            ->where('tenant_id', $tenantId)
            ->find($id);

        return $modelo === null ? null : $this->aEntidad($modelo);
    }

    public function obtenerPorAccion(int $tenantId, int $accionId): ?Plan
    {
        $planId = DB::table('plan_acciones as a')
            ->join('planes as p', 'p.id', '=', 'a.plan_id')
            ->where('p.tenant_id', $tenantId)
            ->where('a.id', $accionId)
            ->value('p.id');

        return $planId === null ? null : $this->obtener($tenantId, (int) $planId);
    }

    public function existeNombre(int $tenantId, int $anio, string $nombreNormalizado, ?int $excluirPlanId = null): bool
    {
        return PlanModel::query()
            ->where('tenant_id', $tenantId)
            ->where('anio', $anio)
            ->where('nombre_normalizado', $nombreNormalizado)
            ->when($excluirPlanId !== null, fn ($q) => $q->where('id', '!=', $excluirPlanId))
            ->exists();
    }

    public function listar(int $tenantId, FiltroPlanes $filtro): array
    {
        $consulta = PlanModel::query()
            ->where('tenant_id', $tenantId)
            ->when($filtro->anio !== null, fn ($q) => $q->where('anio', $filtro->anio))
            ->when($filtro->estado !== null, fn ($q) => $q->where('estado', $filtro->estado->value))
            ->when($filtro->texto !== null && $filtro->texto !== '', fn ($q) => $q->where('nombre_normalizado', 'like', '%'.mb_strtolower(trim($filtro->texto)).'%'));

        $total = (clone $consulta)->count();
        $modelos = $consulta->orderByDesc('anio')->orderByDesc('numero')
            ->forPage($filtro->pagina, $filtro->porPagina)->get();

        $agregados = $modelos->isEmpty() ? collect() : DB::table('plan_acciones')
            ->whereIn('plan_id', $modelos->pluck('id'))
            ->where('cancelada', false)
            ->groupBy('plan_id')
            ->selectRaw('plan_id, count(*) as cantidad, coalesce(sum(costo), 0) as costo')
            ->get()->keyBy('plan_id');

        $items = $modelos->map(fn (PlanModel $m) => [
            'plan' => $this->aEntidad($m, false),
            'cantidad_acciones' => (int) ($agregados[$m->id]->cantidad ?? 0),
            'costo_total' => round((float) ($agregados[$m->id]->costo ?? 0), 2),
        ])->all();

        return ['items' => $items, 'total' => $total];
    }

    public function listarAcciones(int $tenantId, FiltroAcciones $filtro): array
    {
        $consulta = DB::table('plan_acciones as a')
            ->join('planes as p', 'p.id', '=', 'a.plan_id')
            ->where('p.tenant_id', $tenantId)
            ->when($filtro->estadoPlan !== null, fn (Builder $q) => $q->where('p.estado', $filtro->estadoPlan->value))
            ->when($filtro->tipo !== null, fn (Builder $q) => $q->where('a.tipo', $filtro->tipo->value))
            ->when($filtro->anio !== null, fn (Builder $q) => $q->where('p.anio', $filtro->anio))
            ->when($filtro->planId !== null, fn (Builder $q) => $q->where('p.id', $filtro->planId))
            ->when($filtro->competenciaId !== null, fn (Builder $q) => $q->whereExists(
                fn (Builder $s) => $s->select(DB::raw(1))->from('plan_accion_competencias as c')
                    ->whereColumn('c.plan_accion_id', 'a.id')->where('c.competencia_id', $filtro->competenciaId),
            ))
            ->when($filtro->desde !== null, fn (Builder $q) => $q->whereNotNull('a.fecha_fin')->where('a.fecha_fin', '>=', $filtro->desde))
            ->when($filtro->hasta !== null, fn (Builder $q) => $q->whereNotNull('a.fecha_inicio')->where('a.fecha_inicio', '<=', $filtro->hasta));

        $total = (clone $consulta)->count();
        $filas = $consulta
            ->orderByRaw('a.fecha_inicio is null')->orderBy('a.fecha_inicio')->orderBy('a.id')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get(['a.id', 'a.plan_id', 'p.codigo as plan_codigo', 'p.nombre as plan_nombre', 'p.estado as plan_estado', 'a.nombre', 'a.tipo',
                'a.fecha_inicio', 'a.fecha_fin', 'a.formador', 'a.capacidad_maxima', 'a.costo', 'a.cancelada', 'a.motivo_cancelacion']);

        $ids = $filas->pluck('id')->all();
        $competencias = $ids === [] ? collect() : DB::table('plan_accion_competencias')->whereIn('plan_accion_id', $ids)->orderBy('id')->get()->groupBy('plan_accion_id');
        $inscritos = $ids === [] ? collect() : DB::table('plan_accion_participantes')->whereIn('plan_accion_id', $ids)
            ->groupBy('plan_accion_id')->selectRaw('plan_accion_id, count(*) as n')->pluck('n', 'plan_accion_id');

        $items = $filas->map(fn ($f) => [
            'accion_id' => (int) $f->id,
            'plan_id' => (int) $f->plan_id,
            'plan_codigo' => (string) $f->plan_codigo,
            'plan_nombre' => (string) $f->plan_nombre,
            'plan_estado' => EstadoPlan::from($f->plan_estado),
            'nombre' => (string) $f->nombre,
            'tipo' => $f->tipo === null ? null : TipoAccion::from($f->tipo),
            'inicio' => $f->fecha_inicio === null ? null : $this->fecha($f->fecha_inicio),
            'fin' => $f->fecha_fin === null ? null : $this->fecha($f->fecha_fin),
            'formador' => $f->formador,
            'capacidad_maxima' => $f->capacidad_maxima === null ? null : (int) $f->capacidad_maxima,
            'costo' => $f->costo === null ? null : (float) $f->costo,
            'cancelada' => (bool) $f->cancelada,
            'motivo_cancelacion' => $f->motivo_cancelacion,
            'competencia_ids' => ($competencias[$f->id] ?? collect())->pluck('competencia_id')->map(fn ($i) => (int) $i)->all(),
            'inscritos' => (int) ($inscritos[$f->id] ?? 0),
        ])->all();

        return ['items' => $items, 'total' => $total];
    }

    public function cambiarEstadoSi(int $tenantId, int $planId, EstadoPlan $esperado, EstadoPlan $nuevo, ?DateTimeImmutable $fechaAprobacion, int $usuarioId): bool
    {
        $cambios = ['estado' => $nuevo->value, 'updated_by' => $usuarioId, 'updated_at' => now()];
        if ($fechaAprobacion !== null) {
            $cambios['fecha_aprobacion'] = $fechaAprobacion->format('Y-m-d H:i:s');
        }

        return DB::table('planes')
            ->where('tenant_id', $tenantId)->where('id', $planId)->where('estado', $esperado->value)
            ->update($cambios) === 1;
    }

    public function cambiarAprobador(int $tenantId, int $planId, int $aprobadorId, int $usuarioId): void
    {
        DB::table('planes')->where('tenant_id', $tenantId)->where('id', $planId)
            ->update(['aprobador_id' => $aprobadorId, 'updated_by' => $usuarioId, 'updated_at' => now()]);
    }

    public function reemplazarParticipantes(int $accionId, array $colaboradorIds, int $usuarioId): void
    {
        DB::transaction(function () use ($accionId, $colaboradorIds, $usuarioId): void {
            $actuales = PlanAccionParticipanteModel::query()->where('plan_accion_id', $accionId)->pluck('colaborador_id')->map(fn ($i) => (int) $i)->all();
            $quitar = array_diff($actuales, $colaboradorIds);
            if ($quitar !== []) {
                PlanAccionParticipanteModel::query()->where('plan_accion_id', $accionId)->whereIn('colaborador_id', $quitar)->delete();
            }
            foreach (array_diff($colaboradorIds, $actuales) as $nuevo) {
                PlanAccionParticipanteModel::query()->create(['plan_accion_id' => $accionId, 'colaborador_id' => $nuevo, 'created_by' => $usuarioId]);
            }
            PlanAccionModel::query()->whereKey($accionId)->update(['updated_by' => $usuarioId, 'updated_at' => now()]);
        });
    }

    public function cancelarAccion(int $accionId, string $motivo, int $usuarioId): void
    {
        PlanAccionModel::query()->whereKey($accionId)
            ->update(['cancelada' => true, 'motivo_cancelacion' => $motivo, 'updated_by' => $usuarioId, 'updated_at' => now()]);
    }

    public function registrarHistorial(int $planId, ?int $accionId, TipoHistorialPlan $tipo, ?string $anterior, ?string $nuevo, ?string $motivo, int $usuarioId): void
    {
        PlanHistorialModel::query()->create([
            'plan_id' => $planId,
            'plan_accion_id' => $accionId,
            'tipo' => $tipo->value,
            'valor_anterior' => $anterior,
            'valor_nuevo' => $nuevo,
            'motivo' => $motivo,
            'user_id' => $usuarioId,
        ]);
    }

    public function historial(int $tenantId, int $planId, int $pagina, int $porPagina): array
    {
        $consulta = DB::table('plan_historial as h')
            ->join('planes as p', 'p.id', '=', 'h.plan_id')
            ->leftJoin('plan_acciones as a', 'a.id', '=', 'h.plan_accion_id')
            ->where('p.tenant_id', $tenantId)
            ->where('h.plan_id', $planId);

        $total = (clone $consulta)->count();
        $filas = $consulta->orderByDesc('h.id')->forPage($pagina, $porPagina)
            ->get(['h.id', 'h.tipo', 'h.plan_accion_id', 'a.nombre as accion_nombre', 'h.valor_anterior', 'h.valor_nuevo', 'h.motivo', 'h.user_id', 'h.created_at']);

        return [
            'items' => $filas->map(fn ($f) => [
                'id' => (int) $f->id,
                'tipo' => TipoHistorialPlan::from($f->tipo),
                'accion_id' => $f->plan_accion_id === null ? null : (int) $f->plan_accion_id,
                'accion_nombre' => $f->accion_nombre,
                'valor_anterior' => $f->valor_anterior,
                'valor_nuevo' => $f->valor_nuevo,
                'motivo' => $f->motivo,
                'usuario_id' => (int) $f->user_id,
                'fecha' => $f->created_at === null ? null : new DateTimeImmutable($f->created_at),
            ])->all(),
            'total' => $total,
        ];
    }

    public function inscripcionesParaCruces(int $tenantId, array $colaboradorIds): array
    {
        if ($colaboradorIds === []) {
            return [];
        }

        return DB::table('plan_accion_participantes as pp')
            ->join('plan_acciones as a', 'a.id', '=', 'pp.plan_accion_id')
            ->join('planes as p', 'p.id', '=', 'a.plan_id')
            ->where('p.tenant_id', $tenantId)
            ->where('a.cancelada', false)
            ->whereNotNull('a.fecha_inicio')->whereNotNull('a.fecha_fin')
            ->whereIn('pp.colaborador_id', array_values(array_unique($colaboradorIds)))
            ->orderBy('pp.colaborador_id')->orderBy('a.id')
            ->get(['a.id as accion_id', 'a.plan_id', 'pp.colaborador_id', 'a.fecha_inicio', 'a.fecha_fin'])
            ->map(fn ($f) => [
                'accion_id' => (int) $f->accion_id,
                'plan_id' => (int) $f->plan_id,
                'colaborador_id' => (int) $f->colaborador_id,
                'inicio' => $this->fecha($f->fecha_inicio)->format('Y-m-d'),
                'fin' => $this->fecha($f->fecha_fin)->format('Y-m-d'),
            ])->all();
    }

    public function accionesDeColaborador(int $tenantId, int $colaboradorId): array
    {
        $filas = DB::table('plan_accion_participantes as pp')
            ->join('plan_acciones as a', 'a.id', '=', 'pp.plan_accion_id')
            ->join('planes as p', 'p.id', '=', 'a.plan_id')
            ->where('p.tenant_id', $tenantId)
            ->where('pp.colaborador_id', $colaboradorId)
            ->whereIn('p.estado', [EstadoPlan::Aprobado->value, EstadoPlan::Ejecutando->value, EstadoPlan::Cerrado->value])
            ->orderByRaw('a.fecha_inicio is null')->orderBy('a.fecha_inicio')->orderBy('a.id')
            ->get(['a.id', 'a.plan_id', 'p.nombre as plan_nombre', 'p.anio as plan_anio', 'a.nombre', 'a.tipo', 'a.fecha_inicio', 'a.fecha_fin',
                'a.formador', 'a.duracion_horas', 'a.cancelada', 'a.motivo_cancelacion']);

        $competencias = $filas->isEmpty() ? collect() : DB::table('plan_accion_competencias')->whereIn('plan_accion_id', $filas->pluck('id'))->orderBy('id')->get()->groupBy('plan_accion_id');

        return $filas->map(fn ($f) => [
            'accion_id' => (int) $f->id,
            'plan_id' => (int) $f->plan_id,
            'plan_nombre' => (string) $f->plan_nombre,
            'plan_anio' => (int) $f->plan_anio,
            'nombre' => (string) $f->nombre,
            'tipo' => $f->tipo === null ? null : TipoAccion::from($f->tipo),
            'inicio' => $f->fecha_inicio === null ? null : $this->fecha($f->fecha_inicio),
            'fin' => $f->fecha_fin === null ? null : $this->fecha($f->fecha_fin),
            'formador' => $f->formador,
            'duracion_horas' => $f->duracion_horas === null ? null : (float) $f->duracion_horas,
            'cancelada' => (bool) $f->cancelada,
            'motivo_cancelacion' => $f->motivo_cancelacion,
            'competencia_ids' => ($competencias[$f->id] ?? collect())->pluck('competencia_id')->map(fn ($i) => (int) $i)->all(),
        ])->all();
    }

    public function accionesDePlanesEjecutando(int $tenantId): array
    {
        $filas = DB::table('plan_acciones as a')
            ->join('planes as p', 'p.id', '=', 'a.plan_id')
            ->where('p.tenant_id', $tenantId)
            ->where('p.estado', EstadoPlan::Ejecutando->value)
            ->where('a.cancelada', false)
            ->orderBy('a.id')
            ->get(['a.id', 'a.plan_id', 'a.nombre', 'a.tipo', 'a.fecha_inicio', 'a.fecha_fin']);

        $participantes = $filas->isEmpty() ? collect() : DB::table('plan_accion_participantes')->whereIn('plan_accion_id', $filas->pluck('id'))->orderBy('id')->get()->groupBy('plan_accion_id');

        return $filas->map(fn ($f) => [
            'accion_id' => (int) $f->id,
            'plan_id' => (int) $f->plan_id,
            'nombre' => (string) $f->nombre,
            'tipo' => $f->tipo,
            'fecha_inicio' => $f->fecha_inicio === null ? null : $this->fecha($f->fecha_inicio)->format('Y-m-d'),
            'fecha_fin' => $f->fecha_fin === null ? null : $this->fecha($f->fecha_fin)->format('Y-m-d'),
            'participantes' => ($participantes[$f->id] ?? collect())->pluck('colaborador_id')->map(fn ($i) => (int) $i)->all(),
        ])->all();
    }

    public function planesConAcciones(int $tenantId, array $filtros): array
    {
        $planes = PlanModel::query()
            ->with('acciones.participantes')
            ->where('tenant_id', $tenantId)
            ->when(isset($filtros['anio']), fn ($q) => $q->where('anio', (int) $filtros['anio']))
            ->when(isset($filtros['estado']), fn ($q) => $q->where('estado', (string) $filtros['estado']))
            ->when(isset($filtros['plan_id']), fn ($q) => $q->where('id', (int) $filtros['plan_id']))
            ->orderBy('anio')->orderBy('numero')
            ->get();

        return $planes->map(function (PlanModel $m) {
            $plan = $this->aEntidad($m);

            return [
                'id' => (int) $m->id,
                'codigo' => $plan->codigo(),
                'nombre' => $plan->nombre,
                'anio' => $plan->anio,
                'estado' => $plan->estado->value,
                'costo_total' => $plan->costoTotal(),
                'horas_totales' => $plan->horasTotales(),
                'acciones' => array_map(fn (AccionFormativa $a) => [
                    'id' => $a->id,
                    'nombre' => $a->nombre,
                    'competencia_ids' => $a->competenciaIds,
                    'participante_ids' => $a->colaboradorIds,
                    'tipo' => $a->tipo?->value,
                    'horas' => $a->duracionHoras,
                    'costo' => $a->costo,
                    'cancelada' => $a->cancelada,
                    'fecha_inicio' => $a->fechaInicio?->format('Y-m-d'),
                    'fecha_fin' => $a->fechaFin?->format('Y-m-d'),
                    'inscritos' => $a->inscritos(),
                ], $plan->acciones),
            ];
        })->all();
    }

    public function accionesPorIds(int $tenantId, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        return $this->aAccionesDeActividad($this->consultaAccionesDeActividad($tenantId)->whereIn('a.id', $ids)->get());
    }

    public function accionesElegiblesParaActividad(int $tenantId, array $idsConActividad, ?int $planId, ?string $q, int $pagina, int $porPagina): array
    {
        $consulta = fn () => $this->consultaAccionesDeActividad($tenantId)
            ->where('p.estado', EstadoPlan::Ejecutando->value)
            ->where('a.cancelada', false)
            ->when($idsConActividad !== [], fn ($c) => $c->whereNotIn('a.id', $idsConActividad))
            ->when($planId !== null, fn ($c) => $c->where('a.plan_id', $planId))
            ->when($q !== null && trim($q) !== '', fn ($c) => $c->whereRaw('lower(a.nombre) like ?', ['%'.mb_strtolower(trim($q)).'%']));

        $total = $consulta()->count();
        $filas = $consulta()->orderBy('a.id')->forPage($pagina, $porPagina)->get();

        return ['items' => array_values($this->aAccionesDeActividad($filas)), 'total' => $total];
    }

    public function idsDeAccionesDeColaboradorEnEjecucion(int $tenantId, int $colaboradorId): array
    {
        return DB::table('plan_accion_participantes as pp')
            ->join('plan_acciones as a', 'a.id', '=', 'pp.plan_accion_id')
            ->join('planes as p', 'p.id', '=', 'a.plan_id')
            ->where('p.tenant_id', $tenantId)
            ->where('pp.colaborador_id', $colaboradorId)
            ->whereIn('p.estado', [EstadoPlan::Ejecutando->value, EstadoPlan::Cerrado->value])
            ->orderBy('a.id')
            ->pluck('a.id')->map(fn ($i) => (int) $i)->all();
    }

    public function idsDeAccionesVinculablesDeColaborador(int $tenantId, int $colaboradorId): array
    {
        return DB::table('plan_accion_participantes as pp')
            ->join('plan_acciones as a', 'a.id', '=', 'pp.plan_accion_id')
            ->join('planes as p', 'p.id', '=', 'a.plan_id')
            ->where('p.tenant_id', $tenantId)
            ->where('pp.colaborador_id', $colaboradorId)
            ->whereIn('p.estado', [EstadoPlan::Aprobado->value, EstadoPlan::Ejecutando->value])
            ->where('a.cancelada', false)
            ->orderBy('a.id')
            ->pluck('a.id')->map(fn ($i) => (int) $i)->all();
    }

    public function planesParaActividades(int $tenantId): array
    {
        return DB::table('planes')
            ->where('tenant_id', $tenantId)
            ->whereIn('estado', [EstadoPlan::Ejecutando->value, EstadoPlan::Cerrado->value])
            ->orderByDesc('anio')->orderByDesc('numero')
            ->get(['id', 'codigo', 'nombre', 'anio', 'estado'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'codigo' => (string) $p->codigo, 'nombre' => (string) $p->nombre, 'anio' => (int) $p->anio, 'estado' => (string) $p->estado])
            ->all();
    }

    private function consultaAccionesDeActividad(int $tenantId): Builder
    {
        return DB::table('plan_acciones as a')
            ->join('planes as p', 'p.id', '=', 'a.plan_id')
            ->where('p.tenant_id', $tenantId)
            ->select(['a.id', 'a.plan_id', 'p.codigo as plan_codigo', 'p.nombre as plan_nombre', 'p.estado as plan_estado', 'a.nombre', 'a.tipo',
                'a.fecha_inicio', 'a.fecha_fin', 'a.duracion_horas', 'a.formador', 'a.capacidad_maxima', 'a.cancelada']);
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return array<int, AccionDeActividad>
     */
    private function aAccionesDeActividad($filas): array
    {
        if ($filas->isEmpty()) {
            return [];
        }
        $competencias = DB::table('plan_accion_competencias')->whereIn('plan_accion_id', $filas->pluck('id'))->orderBy('id')->get()->groupBy('plan_accion_id');
        $participantes = DB::table('plan_accion_participantes')->whereIn('plan_accion_id', $filas->pluck('id'))->orderBy('id')->get()->groupBy('plan_accion_id');

        $resultado = [];
        foreach ($filas as $f) {
            $resultado[(int) $f->id] = new AccionDeActividad(
                (int) $f->id,
                (int) $f->plan_id,
                (string) $f->plan_codigo,
                (string) $f->plan_nombre,
                EstadoPlan::from($f->plan_estado),
                (string) $f->nombre,
                $f->tipo === null ? null : TipoAccion::from($f->tipo),
                $f->fecha_inicio === null ? null : $this->fecha($f->fecha_inicio),
                $f->fecha_fin === null ? null : $this->fecha($f->fecha_fin),
                $f->duracion_horas === null ? null : (float) $f->duracion_horas,
                $f->formador,
                $f->capacidad_maxima === null ? null : (int) $f->capacidad_maxima,
                (bool) $f->cancelada,
                ($competencias[$f->id] ?? collect())->pluck('competencia_id')->map(fn ($i) => (int) $i)->all(),
                ($participantes[$f->id] ?? collect())->pluck('colaborador_id')->map(fn ($i) => (int) $i)->all(),
            );
        }

        return $resultado;
    }

    private function insertarAccion(int $planId, AccionFormativa $accion, int $usuarioId): int
    {
        $modelo = PlanAccionModel::query()->create([
            'plan_id' => $planId,
            'curso_id' => $accion->cursoId,
            'nombre' => $accion->nombre,
            'tipo' => $accion->tipo?->value,
            'fecha_inicio' => $accion->fechaInicio?->format('Y-m-d'),
            'fecha_fin' => $accion->fechaFin?->format('Y-m-d'),
            'duracion_horas' => $accion->duracionHoras,
            'formador' => $accion->formador,
            'capacidad_maxima' => $accion->capacidadMaxima,
            'costo' => $accion->costo,
            'cancelada' => $accion->cancelada,
            'motivo_cancelacion' => $accion->motivoCancelacion,
            'created_by' => $usuarioId,
            'updated_by' => $usuarioId,
        ]);
        $this->sincronizarHijos($modelo->id, $accion, $usuarioId);

        return $modelo->id;
    }

    private function actualizarAccion(AccionFormativa $accion, int $usuarioId): void
    {
        PlanAccionModel::query()->whereKey($accion->id)->update([
            'curso_id' => $accion->cursoId,
            'nombre' => $accion->nombre,
            'tipo' => $accion->tipo?->value,
            'fecha_inicio' => $accion->fechaInicio?->format('Y-m-d'),
            'fecha_fin' => $accion->fechaFin?->format('Y-m-d'),
            'duracion_horas' => $accion->duracionHoras,
            'formador' => $accion->formador,
            'capacidad_maxima' => $accion->capacidadMaxima,
            'costo' => $accion->costo,
            'updated_by' => $usuarioId,
            'updated_at' => now(),
        ]);
        $this->sincronizarHijos((int) $accion->id, $accion, $usuarioId);
    }

    private function sincronizarHijos(int $accionId, AccionFormativa $accion, int $usuarioId): void
    {
        $competencias = PlanAccionCompetenciaModel::query()->where('plan_accion_id', $accionId)->pluck('competencia_id')->map(fn ($i) => (int) $i)->all();
        $quitar = array_diff($competencias, $accion->competenciaIds);
        if ($quitar !== []) {
            PlanAccionCompetenciaModel::query()->where('plan_accion_id', $accionId)->whereIn('competencia_id', $quitar)->delete();
        }
        foreach (array_diff($accion->competenciaIds, $competencias) as $competenciaId) {
            PlanAccionCompetenciaModel::query()->create(['plan_accion_id' => $accionId, 'competencia_id' => $competenciaId]);
        }

        $participantes = PlanAccionParticipanteModel::query()->where('plan_accion_id', $accionId)->pluck('colaborador_id')->map(fn ($i) => (int) $i)->all();
        $quitar = array_diff($participantes, $accion->colaboradorIds);
        if ($quitar !== []) {
            PlanAccionParticipanteModel::query()->where('plan_accion_id', $accionId)->whereIn('colaborador_id', $quitar)->delete();
        }
        foreach (array_diff($accion->colaboradorIds, $participantes) as $colaboradorId) {
            PlanAccionParticipanteModel::query()->create(['plan_accion_id' => $accionId, 'colaborador_id' => $colaboradorId, 'created_by' => $usuarioId]);
        }
    }

    private function aEntidad(PlanModel $m, bool $conAcciones = true): Plan
    {
        $acciones = $conAcciones ? $m->acciones->map(fn (PlanAccionModel $a) => new AccionFormativa(
            (int) $a->id,
            (string) $a->nombre,
            $a->curso_id === null ? null : (int) $a->curso_id,
            $a->tipo === null ? null : TipoAccion::from($a->tipo),
            $a->fecha_inicio === null ? null : $this->fecha($a->fecha_inicio),
            $a->fecha_fin === null ? null : $this->fecha($a->fecha_fin),
            $a->duracion_horas === null ? null : (float) $a->duracion_horas,
            $a->formador,
            $a->capacidad_maxima === null ? null : (int) $a->capacidad_maxima,
            $a->costo === null ? null : (float) $a->costo,
            (bool) $a->cancelada,
            $a->motivo_cancelacion,
            $a->competencias->pluck('competencia_id')->map(fn ($i) => (int) $i)->all(),
            $a->participantes->pluck('colaborador_id')->map(fn ($i) => (int) $i)->all(),
        ))->all() : [];

        return new Plan(
            (int) $m->id,
            (int) $m->tenant_id,
            (int) $m->numero,
            (string) $m->nombre,
            (string) $m->nombre_normalizado,
            (int) $m->anio,
            $m->descripcion,
            EstadoPlan::from($m->estado),
            $m->aprobador_id === null ? null : (int) $m->aprobador_id,
            $m->origen_dnf_id === null ? null : (int) $m->origen_dnf_id,
            $m->fecha_aprobacion === null ? null : DateTimeImmutable::createFromInterface($m->fecha_aprobacion),
            (int) $m->created_by,
            (int) $m->updated_by,
            $m->created_at === null ? null : DateTimeImmutable::createFromInterface($m->created_at),
            $m->updated_at === null ? null : DateTimeImmutable::createFromInterface($m->updated_at),
            $acciones,
        );
    }

    private function fecha(mixed $valor): DateTimeImmutable
    {
        return new DateTimeImmutable(substr((string) $valor, 0, 10));
    }
}
