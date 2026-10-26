<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\Actuacion;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\MovimientoIncompatible;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudModificada;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeActuacion;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EloquentActuacionRepository implements ActuacionRepositoryInterface
{
    private const TOPE_DE_SOLICITUDES_PROPIAS = 200;

    public function porId(int $tenantId, int $id): ?Actuacion
    {
        $fila = $this->base()->where('a.tenant_id', $tenantId)->where('a.id', $id)->first();

        return $fila === null ? null : $this->hidratar([$fila])[0];
    }

    public function porOperacion(int $tenantId, string $operacionId): ?array
    {
        $fila = $this->base()->where('a.tenant_id', $tenantId)->where('a.operacion_id', $operacionId)->first();
        if ($fila === null) {
            return null;
        }

        return ['actuacion' => $this->hidratar([$fila])[0], 'hash' => (string) $fila->operacion_hash, 'actor' => (int) $fila->presentada_por];
    }

    public function transicionPorOperacion(int $tenantId, string $operacionId): ?array
    {
        $fila = DB::table('actuacion_transiciones')->where('tenant_id', $tenantId)->where('operacion_id', $operacionId)->first();
        if ($fila === null) {
            return null;
        }

        return [
            'actuacion_id' => (int) $fila->actuacion_id,
            'hash' => (string) $fila->operacion_hash,
            'actor_id' => $fila->actor_id === null ? null : (int) $fila->actor_id,
            'evento' => (string) $fila->evento,
        ];
    }

    public function insertar(array $comun, array $detalle, array $documentoIds): Actuacion
    {
        $ahora = now();
        $tenantId = (int) $comun['tenant_id'];

        try {
            $id = (int) DB::table('actuaciones_personal')->insertGetId($comun + ['created_at' => $ahora, 'updated_at' => $ahora]);
            $tabla = $comun['categoria'] === 'Movimiento' ? 'movimientos_personal' : 'novedades_personal';
            DB::table($tabla)->insert($detalle + ['actuacion_id' => $id, 'tenant_id' => $tenantId]);

            foreach (array_values(array_unique($documentoIds)) as $documentoId) {
                DB::table('actuacion_documentos')->insert([
                    'actuacion_id' => $id, 'documento_id' => $documentoId, 'tenant_id' => $tenantId, 'created_at' => $ahora,
                ]);
            }

            DB::table('actuacion_transiciones')->insert([
                'tenant_id' => $tenantId,
                'actuacion_id' => $id,
                'evento' => 'Presentada',
                'estado_anterior' => null,
                'estado_nuevo' => $comun['estado'],
                'actor_tipo' => 'usuario',
                'actor_id' => $comun['presentada_por'],
                'justificacion' => null,
                'detalle' => null,
                'operacion_id' => null,
                'operacion_hash' => null,
                'created_at' => $ahora,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictoDeUnicidad;
        }

        return $this->porId($tenantId, $id) ?? throw new ConflictoDeUnicidad;
    }

    public function cambiarEstado(
        Actuacion $actual,
        EstadoDeActuacion $nuevo,
        string $evento,
        ?int $actorId,
        ?string $justificacion,
        array $detalleDeTransicion = [],
        ?string $operacionId = null,
        ?string $operacionHash = null,
        array $novedad = [],
        array $movimiento = [],
    ): Actuacion {
        $ahora = now();

        try {
            $filas = DB::table('actuaciones_personal')
                ->where('id', $actual->id)
                ->where('tenant_id', $actual->tenantId)
                ->where('version', $actual->version)
                ->where('estado', $actual->estado->value)
                ->update([
                    'estado' => $nuevo->value,
                    'version' => DB::raw('version + 1'),
                    'updated_by' => $actorId ?? 0,
                    'updated_at' => $ahora,
                ]);
            if ($filas !== 1) {
                throw new SolicitudModificada;
            }

            if ($novedad !== []) {
                DB::table('novedades_personal')->where('actuacion_id', $actual->id)->update($novedad);
            }
            if ($movimiento !== []) {
                DB::table('movimientos_personal')->where('actuacion_id', $actual->id)->update($movimiento);
            }

            DB::table('actuacion_transiciones')->insert([
                'tenant_id' => $actual->tenantId,
                'actuacion_id' => $actual->id,
                'evento' => $evento,
                'estado_anterior' => $actual->estado->value,
                'estado_nuevo' => $nuevo->value,
                'actor_tipo' => $actorId === null ? 'sistema' : 'usuario',
                'actor_id' => $actorId,
                'justificacion' => $justificacion,
                'detalle' => $detalleDeTransicion === [] ? null : json_encode($detalleDeTransicion, JSON_UNESCAPED_UNICODE),
                'operacion_id' => $operacionId,
                'operacion_hash' => $operacionHash,
                'created_at' => $ahora,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw str_contains($e->getMessage(), 'actuaciones_personal.colaborador_id') ? new MovimientoIncompatible : new ConflictoDeUnicidad;
        }

        return $this->porId($actual->tenantId, $actual->id) ?? throw new SolicitudModificada;
    }

    public function listar(int $tenantId, array $filtros, int $pagina, int $porPagina): array
    {
        $consulta = DB::table('actuaciones_personal as a')
            ->join('colaboradores as c', 'c.id', '=', 'a.colaborador_id')
            ->leftJoin('novedades_personal as n', 'n.actuacion_id', '=', 'a.id')
            ->leftJoin('movimientos_personal as m', 'm.actuacion_id', '=', 'a.id')
            ->where('a.tenant_id', $tenantId);

        $this->aplicarFiltros($consulta, $filtros);

        $total = (clone $consulta)->count('a.id');
        $filas = $consulta
            ->orderByDesc('a.presentada_en')->orderByDesc('a.id')
            ->forPage($pagina, $porPagina)
            ->get([
                'a.id', 'a.tipo', 'a.categoria', 'a.estado', 'a.version', 'a.presentada_en', 'a.fecha_presentacion',
                'a.presentada_por', 'a.en_nombre_de_colaborador', 'a.colaborador_id',
                'c.codigo as colaborador_codigo', 'c.nombres as colaborador_nombres', 'c.apellidos as colaborador_apellidos',
                'n.fecha_inicio', 'n.fecha_fin', 'm.fecha_efectiva', 'm.aplicacion_estado',
            ]);

        $nombres = $this->nombresDeCuentas($filas->pluck('presentada_por')->map(fn ($v) => (int) $v)->unique()->values()->all());

        $items = [];
        foreach ($filas as $fila) {
            $items[] = [
                'id' => (int) $fila->id,
                'tipo' => (string) $fila->tipo,
                'categoria' => (string) $fila->categoria,
                'estado' => (string) $fila->estado,
                'version' => (int) $fila->version,
                'colaborador' => [
                    'id' => (int) $fila->colaborador_id,
                    'codigo' => (string) $fila->colaborador_codigo,
                    'nombre' => trim($fila->colaborador_nombres.' '.$fila->colaborador_apellidos),
                ],
                'presentada_en' => $this->iso($fila->presentada_en),
                'fecha_presentacion' => (string) $fila->fecha_presentacion,
                'presentada_por' => ['id' => (int) $fila->presentada_por, 'nombre' => $nombres[(int) $fila->presentada_por] ?? null],
                'en_nombre_de_colaborador' => (bool) $fila->en_nombre_de_colaborador,
                'periodo' => $fila->fecha_inicio === null ? null : ['inicio' => (string) $fila->fecha_inicio, 'fin' => (string) $fila->fecha_fin],
                'fecha_efectiva' => $fila->fecha_efectiva === null ? null : (string) $fila->fecha_efectiva,
                'aplicacion_estado' => $fila->aplicacion_estado === null ? null : (string) $fila->aplicacion_estado,
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    public function resumen(int $tenantId): array
    {
        $conteos = DB::table('actuaciones_personal')->where('tenant_id', $tenantId)
            ->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $de = fn (string ...$estados) => (int) array_sum(array_map(fn (string $e) => (int) ($conteos[$e] ?? 0), $estados));

        return [
            'pendientes' => $de('Presentada', 'Informada'),
            'en_revision' => $de('EnRevision'),
            'programados' => $de('Programada', 'Aprobada') - $this->novedadesAprobadas($tenantId),
            'aplicados' => $de('Aplicada'),
            'incidencias' => $de('IncidenciaDeAplicacion'),
        ];
    }

    public function delColaborador(int $tenantId, int $colaboradorId): array
    {
        $filas = $this->base()
            ->where('a.tenant_id', $tenantId)->where('a.colaborador_id', $colaboradorId)
            ->orderByDesc('a.presentada_en')->orderByDesc('a.id')
            ->limit(self::TOPE_DE_SOLICITUDES_PROPIAS)
            ->get()->all();

        return $this->hidratar($filas);
    }

    public function periodosDeNovedades(int $tenantId, int $colaboradorId): array
    {
        return DB::table('actuaciones_personal as a')
            ->join('novedades_personal as n', 'n.actuacion_id', '=', 'a.id')
            ->where('a.tenant_id', $tenantId)->where('a.colaborador_id', $colaboradorId)
            ->orderBy('n.fecha_inicio')
            ->get(['a.id', 'a.tipo', 'a.estado', 'n.fecha_inicio', 'n.fecha_fin'])
            ->map(fn ($f) => [
                'id' => (int) $f->id, 'tipo' => (string) $f->tipo, 'estado' => (string) $f->estado,
                'inicio' => (string) $f->fecha_inicio, 'fin' => (string) $f->fecha_fin,
            ])->all();
    }

    public function historial(int $tenantId, int $actuacionId): array
    {
        $filas = DB::table('actuacion_transiciones')->where('tenant_id', $tenantId)->where('actuacion_id', $actuacionId)
            ->orderBy('id')->get();
        $nombres = $this->nombresDeCuentas($filas->pluck('actor_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all());

        return $filas->map(fn ($f) => [
            'evento' => (string) $f->evento,
            'estado_anterior' => $f->estado_anterior === null ? null : (string) $f->estado_anterior,
            'estado_nuevo' => (string) $f->estado_nuevo,
            'actor' => $f->actor_tipo === 'sistema'
                ? ['tipo' => 'sistema', 'id' => null, 'nombre' => 'Sistema']
                : ['tipo' => 'usuario', 'id' => (int) $f->actor_id, 'nombre' => $nombres[(int) $f->actor_id] ?? null],
            'justificacion' => $f->justificacion === null ? null : (string) $f->justificacion,
            'detalle' => $f->detalle === null ? null : json_decode((string) $f->detalle, true),
            'en' => $this->iso($f->created_at),
        ])->all();
    }

    public function tenantsConMovimientosPorAplicar(): array
    {
        return DB::table('movimientos_personal as m')
            ->join('actuaciones_personal as a', 'a.id', '=', 'm.actuacion_id')
            ->whereIn('a.estado', ['Aprobada', 'Programada'])
            ->where('m.aplicacion_estado', 'Pendiente')
            ->distinct()->orderBy('a.tenant_id')->pluck('a.tenant_id')
            ->map(fn ($id) => (int) $id)->all();
    }

    public function movimientosPorAplicar(int $tenantId, string $hastaFecha, int $limite): array
    {
        return DB::table('movimientos_personal as m')
            ->join('actuaciones_personal as a', 'a.id', '=', 'm.actuacion_id')
            ->where('a.tenant_id', $tenantId)
            ->whereIn('a.estado', ['Aprobada', 'Programada'])
            ->where('m.aplicacion_estado', 'Pendiente')
            ->where('m.fecha_efectiva', '<=', $hastaFecha)
            ->orderBy('m.fecha_efectiva')->orderBy('a.id')
            ->limit($limite)
            ->pluck('a.id')->map(fn ($id) => (int) $id)->all();
    }

    public function hechosAplicados(int $tenantId, int $colaboradorId): array
    {
        return DB::table('actuaciones_personal as a')
            ->join('movimientos_personal as m', 'm.actuacion_id', '=', 'a.id')
            ->where('a.tenant_id', $tenantId)->where('a.colaborador_id', $colaboradorId)->where('a.estado', 'Aplicada')
            ->orderBy('m.fecha_efectiva')->orderBy('a.id')
            ->get(['a.id', 'a.vinculo_id', 'a.tipo', 'a.politica_numero', 'm.fecha_efectiva', 'm.posicion_destino_id', 'm.aplicada_en'])
            ->map(fn ($f) => [
                'actuacion_id' => (int) $f->id,
                'vinculo_id' => (int) $f->vinculo_id,
                'tipo' => (string) $f->tipo,
                'fecha_efectiva' => (string) $f->fecha_efectiva,
                'posicion_id' => (int) $f->posicion_destino_id,
                'politica_numero' => (int) $f->politica_numero,
                'aplicada_en' => $this->iso($f->aplicada_en),
            ])->all();
    }

    public function fichaResumen(int $tenantId, int $colaboradorId): ?array
    {
        $fila = DB::table('colaboradores')->where('tenant_id', $tenantId)->where('id', $colaboradorId)->first(['codigo', 'nombres', 'apellidos']);

        return $fila === null ? null : ['codigo' => (string) $fila->codigo, 'nombre' => trim($fila->nombres.' '.$fila->apellidos)];
    }

    public function nombresDeCuentas(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id')
            ->map(fn ($n) => (string) $n)->mapWithKeys(fn ($n, $id) => [(int) $id => $n])->all();
    }

    // ------------------------------------------------------------------ Apoyo

    private function base(): Builder
    {
        return DB::table('actuaciones_personal as a')
            ->leftJoin('novedades_personal as n', 'n.actuacion_id', '=', 'a.id')
            ->leftJoin('movimientos_personal as m', 'm.actuacion_id', '=', 'a.id')
            ->select([
                'a.*',
                'n.fecha_inicio as n_fecha_inicio', 'n.fecha_fin as n_fecha_fin', 'n.ocurrida as n_ocurrida',
                'n.cruce_reconocido_por as n_cruce_por', 'n.cruce_reconocido_en as n_cruce_en',
                'n.revision_observacion as n_revision_observacion',
                'm.asignacion_origen_id', 'm.posicion_origen_id', 'm.posicion_destino_id', 'm.fecha_efectiva',
                'm.evaluacion_estado', 'm.evaluacion_contexto', 'm.evaluacion_consultada_en', 'm.aprobada_por', 'm.aprobada_en',
                'm.aplicacion_estado', 'm.aplicada_en', 'm.aplicada_por', 'm.aplicada_por_sistema', 'm.asignacion_nueva_id',
                'm.incidencia_codigo', 'm.incidencia_detalle', 'm.intentos_aplicacion',
            ]);
    }

    /**
     * @param  list<object>  $filas
     * @return list<Actuacion>
     */
    private function hidratar(array $filas): array
    {
        if ($filas === []) {
            return [];
        }

        $documentos = DB::table('actuacion_documentos')
            ->whereIn('actuacion_id', array_map(fn ($f) => (int) $f->id, $filas))
            ->orderBy('documento_id')->get()->groupBy('actuacion_id');

        $salida = [];
        foreach ($filas as $f) {
            $esMovimiento = $f->categoria === 'Movimiento';
            $salida[] = new Actuacion(
                (int) $f->id,
                (int) $f->tenant_id,
                (int) $f->colaborador_id,
                (int) $f->vinculo_id,
                TipoDeActuacion::from((string) $f->tipo),
                EstadoDeActuacion::from((string) $f->estado),
                $f->motivo === null ? null : (string) $f->motivo,
                (int) $f->presentada_por,
                (bool) $f->en_nombre_de_colaborador,
                $this->iso($f->presentada_en),
                (string) $f->fecha_presentacion,
                (int) $f->politica_version_id,
                (int) $f->politica_numero,
                (array) json_decode((string) $f->politica_condiciones, true),
                (string) $f->politica_hash,
                (int) $f->version,
                $esMovimiento ? null : [
                    'fecha_inicio' => (string) $f->n_fecha_inicio,
                    'fecha_fin' => (string) $f->n_fecha_fin,
                    'ocurrida' => (bool) $f->n_ocurrida,
                    'cruce_reconocido_por' => $f->n_cruce_por === null ? null : (int) $f->n_cruce_por,
                    'cruce_reconocido_en' => $f->n_cruce_en === null ? null : $this->iso($f->n_cruce_en),
                    'revision_observacion' => $f->n_revision_observacion === null ? null : (string) $f->n_revision_observacion,
                ],
                $esMovimiento ? [
                    'asignacion_origen_id' => (int) $f->asignacion_origen_id,
                    'posicion_origen_id' => (int) $f->posicion_origen_id,
                    'posicion_destino_id' => (int) $f->posicion_destino_id,
                    'fecha_efectiva' => (string) $f->fecha_efectiva,
                    'evaluacion_estado' => $f->evaluacion_estado === null ? null : (string) $f->evaluacion_estado,
                    'evaluacion_contexto' => $f->evaluacion_contexto === null ? null : json_decode((string) $f->evaluacion_contexto, true),
                    'evaluacion_consultada_en' => $f->evaluacion_consultada_en === null ? null : $this->iso($f->evaluacion_consultada_en),
                    'aprobada_por' => $f->aprobada_por === null ? null : (int) $f->aprobada_por,
                    'aprobada_en' => $f->aprobada_en === null ? null : $this->iso($f->aprobada_en),
                    'aplicacion_estado' => (string) $f->aplicacion_estado,
                    'aplicada_en' => $f->aplicada_en === null ? null : $this->iso($f->aplicada_en),
                    'aplicada_por' => $f->aplicada_por === null ? null : (int) $f->aplicada_por,
                    'aplicada_por_sistema' => (bool) $f->aplicada_por_sistema,
                    'asignacion_nueva_id' => $f->asignacion_nueva_id === null ? null : (int) $f->asignacion_nueva_id,
                    'incidencia_codigo' => $f->incidencia_codigo === null ? null : (string) $f->incidencia_codigo,
                    'incidencia_detalle' => $f->incidencia_detalle === null ? null : (string) $f->incidencia_detalle,
                    'intentos_aplicacion' => (int) $f->intentos_aplicacion,
                ] : null,
                ($documentos[$f->id] ?? collect())->map(fn ($d) => (int) $d->documento_id)->values()->all(),
            );
        }

        return $salida;
    }

    /** @param  array<string, mixed>  $filtros */
    private function aplicarFiltros(Builder $consulta, array $filtros): void
    {
        if (! empty($filtros['tipo'])) {
            $consulta->where('a.tipo', $filtros['tipo']);
        }
        if (! empty($filtros['estado'])) {
            $consulta->where('a.estado', $filtros['estado']);
        }
        if (! empty($filtros['colaborador_id'])) {
            $consulta->where('a.colaborador_id', (int) $filtros['colaborador_id']);
        }

        match ($filtros['grupo'] ?? null) {
            'pendientes' => $consulta->whereIn('a.estado', ['Presentada', 'EnRevision', 'Informada']),
            'resueltas' => $consulta->where(fn ($q) => $q
                ->where(fn ($n) => $n->where('a.categoria', 'Novedad')->whereIn('a.estado', ['Aprobada', 'Rechazada', 'Retirada', 'Revisada', 'Justificada']))
                ->orWhere(fn ($m) => $m->where('a.categoria', 'Movimiento')->where('a.estado', 'Rechazada'))),
            'programados' => $consulta->where('a.categoria', 'Movimiento')->whereIn('a.estado', ['Programada', 'Aprobada']),
            'aplicados' => $consulta->where('a.estado', 'Aplicada'),
            'incidencias' => $consulta->where('a.estado', 'IncidenciaDeAplicacion'),
            default => null,
        };

        $desde = $filtros['desde'] ?? null;
        $hasta = $filtros['hasta'] ?? null;
        if ($desde !== null || $hasta !== null) {
            $consulta->where(function ($q) use ($desde, $hasta) {
                $q->where(function ($n) use ($desde, $hasta) {
                    $n->whereNotNull('n.actuacion_id');
                    if ($hasta !== null) {
                        $n->where('n.fecha_inicio', '<=', $hasta);
                    }
                    if ($desde !== null) {
                        $n->where('n.fecha_fin', '>=', $desde);
                    }
                })->orWhere(function ($m) use ($desde, $hasta) {
                    $m->whereNotNull('m.actuacion_id');
                    if ($hasta !== null) {
                        $m->where('m.fecha_efectiva', '<=', $hasta);
                    }
                    if ($desde !== null) {
                        $m->where('m.fecha_efectiva', '>=', $desde);
                    }
                });
            });
        }
    }

    /** Las novedades aprobadas no son movimientos programados: se descuentan del conteo conjunto. */
    private function novedadesAprobadas(int $tenantId): int
    {
        return (int) DB::table('actuaciones_personal')
            ->where('tenant_id', $tenantId)->where('categoria', 'Novedad')->where('estado', 'Aprobada')->count();
    }

    private function iso(mixed $valor): string
    {
        return Carbon::parse((string) $valor, 'UTC')->toIso8601ZuluString();
    }
}
